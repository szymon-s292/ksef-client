<?php
namespace KSeFClient;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

class Auth {
    private $nip;
    private $mode;
    private $ksef_api;
    private $token_manager;
    private $ksef_token;
    private $ksef_cert;
    private $ksef_pkey;
    private $pkey_passphrase;
    private $enable_token_manager;

    public $auth_status_desc;
    public $auth_status_code;

    public function __construct($nip, KsefMode $mode, $ksef_token = null, $ksef_cert = null, $ksef_pkey = null, $pkey_passphrase = null, $enable_token_manager = false) {
        $this->nip = $nip;
        $this->mode = $mode;

        $this->ksef_token = $ksef_token;
        $this->ksef_cert = $ksef_cert;
        $this->ksef_pkey = $ksef_pkey;
        $this->pkey_passphrase = $pkey_passphrase;

        $this->ksef_api = new KsefApi($mode);
        $this->enable_token_manager = $enable_token_manager;
        if ($enable_token_manager)
            $this->token_manager = new TokenManager($nip, $mode);
    }

    public function auth_with_xades() {
        if (!$this->ksef_cert || !$this->ksef_pkey)
                throw new \Exception("Brak certyfikatu lub klucza prywatnego KSeF");

        $challenge_response = $this->ksef_api->get_challenge();
        $challenge = $challenge_response["challenge"];
        $timestamp = $challenge_response["timestamp"];

        $authTokenRequestBuilder = new AuthTokenRequestBuilder();
        $unsigned_xml = $authTokenRequestBuilder
            ->with_challenge($challenge)
            ->with_nip($this->nip)
            ->with_subject_identifier()
            ->build();

        try {
            $xades_signer = new XadesSigner();
            $signedXml = $xades_signer->sign($unsigned_xml, $this->ksef_pkey, $this->ksef_cert, $this->pkey_passphrase);
            $xades = $signedXml->saveXML();
        } catch (\Throwable $e) {
            throw $e;
        }

        try {
            $json = $this->ksef_api->submit_xades($xades, $this->mode == KsefMode::PROD || $this->mode == KsefMode::DEMO);
            $reference_number = $json["referenceNumber"];
            $authentication_token = $json["authenticationToken"]["token"];
        } catch(BadRequestException $e) {
            $this->auth_status_code = $e->exceptionCode;
            $this->auth_status_desc = $e->exceptionDetails;
            throw $e;
        } catch (\Throwable $e) {
            throw $e;
        }

        do {
            sleep(1);
            $json = $this->ksef_api->get_authentication_status($reference_number, $authentication_token);
            if (!$json || !isset($json["status"]["code"])) {
                    throw new \Exception("Błąd podczas pobierania statusu uwierzytelnienia: " . json_encode($json));
            }

        } while ($json["status"]["code"] == 100);

        $this->auth_status_desc = $json["status"]["description"] ?? "";
        $this->auth_status_code = $json["status"]["code"] ?? "";

        if ($json["status"]["code"] != 200)
                throw new \Exception("Błąd uwierzytelniania: " . $json["status"]["code"] . " " . ($json["status"]["description"] ?? ''));

        $json = $this->ksef_api->get_access_tokens($authentication_token);

        $access_token              = $json['accessToken']['token'] ?? null;
        $access_token_expires_at   = $json['accessToken']['validUntil'] ?? null;
        $refresh_token             = $json['refreshToken']['token'] ?? null;
        $refresh_token_expires_at  = $json['refreshToken']['validUntil'] ?? null;

        if ($this->enable_token_manager)
            $this->token_manager->save_access_and_refresh($access_token, $access_token_expires_at, $refresh_token, $refresh_token_expires_at);

        return [
            "access_token" => $access_token,
            "access_token_expires_at" => $access_token_expires_at,
            "refresh_token" => $refresh_token,
            "refresh_token_expires_at" => $refresh_token_expires_at
        ];
    }

    public function auth_with_token() {
        $ksef_token = $this->ksef_token;
        if (!$ksef_token)
                throw new \Exception("Brak tokenu KSeF");

        $challenge_response = $this->ksef_api->get_challenge();

        $dt = new \DateTime($challenge_response["timestamp"]);
        $milliseconds = ($dt->getTimestamp() * 1000) + (int)$dt->format('v');

        $key_manager = new KeyManager($this->mode);
        $mf_public_key = $key_manager->get_ksef_token_encryption_key();
        $plaintext = $ksef_token . "|" . $milliseconds;

        $publicKey = PublicKeyLoader::load($mf_public_key)
            ->withPadding(RSA::ENCRYPTION_OAEP)
            ->withHash('sha256')
            ->withMGFHash('sha256');

        $encrypted = $publicKey->encrypt($plaintext);

        $token_payload = [
            "challenge" => $challenge_response["challenge"],
            "contextIdentifier" => [
                "type" => "Nip",
                "value" => $this->nip
            ],
            "encryptedToken" => base64_encode($encrypted)
        ];

        try {
            $json = $this->ksef_api->submit_ksef_token(json_encode($token_payload));
            $reference_number = $json["referenceNumber"];
            $authentication_token = $json["authenticationToken"]["token"];
        } catch(BadRequestException $e) {
            $this->auth_status_code = $e->exceptionCode;
            $this->auth_status_desc = $e->exceptionDetails;
            throw $e;
        } catch (\Throwable $e) {
            throw $e;
        }

        do {
            sleep(1);
            $json = $this->ksef_api->get_authentication_status($reference_number, $authentication_token);
            if (!$json || !isset($json["status"]["code"])) {
                    throw new \Exception("Błąd podczas pobierania statusu uwierzytelnienia: " . json_encode($json));
            }

        } while ($json["status"]["code"] == 100);

        $this->auth_status_desc = $json["status"]["description"] ?? "";
        $this->auth_status_code = $json["status"]["code"] ?? "";

        if ($json["status"]["code"] != 200)
                throw new \Exception("Błąd uwierzytelniania: " . $json["status"]["code"] . " " . ($json["status"]["description"] ?? ''));

        $json = $this->ksef_api->get_access_tokens($authentication_token);

        $access_token              = $json['accessToken']['token'] ?? null;
        $access_token_expires_at   = $json['accessToken']['validUntil'] ?? null;
        $refresh_token             = $json['refreshToken']['token'] ?? null;
        $refresh_token_expires_at  = $json['refreshToken']['validUntil'] ?? null;

        if ($this->enable_token_manager)
            $this->token_manager->save_access_and_refresh($access_token, $access_token_expires_at, $refresh_token, $refresh_token_expires_at);

        return [
            "access_token" => $access_token,
            "access_token_expires_at" => $access_token_expires_at,
            "refresh_token" => $refresh_token,
            "refresh_token_expires_at" => $refresh_token_expires_at
        ];
    }

    public function get_access_token() {
        $access_token = $this->enable_token_manager ? $this->token_manager->get_access_token() : null;

        if ($access_token == null) {
            if($this->ksef_cert && $this->ksef_pkey) {
                return $this->auth_with_xades();
            } else if($this->ksef_token) {
                return $this->auth_with_token();
            } else {
                    throw new \Exception("Brak danych uwierzytelniających w KSeF");
            }
        } else {
            $this->auth_status_code = 200;
            $this->auth_status_desc = "Uwierzytelnianie zakończone sukcesem";
        }

        return $access_token;
    }
}
