<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once "auth.php";
require_once "http.php";
require_once "crypto_service.php";

use phpseclib3\Crypt\AES;

class Invoice {
    private $ksef_api;
    private $auth;
    private $crypto;
    private $symetric_key;
    private $iv;
    public $session_reference_number;
    public $invoice_reference_number;

    public function __construct(Auth $auth, KsefMode $mode) {
        $this->ksef_api = new KsefApi($mode);
        $this->auth = $auth;
        $this->crypto = new CryptoService($mode);
        $this->symetric_key = random_bytes(32);
        $this->iv = random_bytes(16);
    }

    public function start_session($system_code, $schema_version, $value) {
        $access_token = $this->auth->get_access_token();
        $encrypted_symetric_key = $this->crypto->symetric_key_encryption($this->symetric_key);
            
        $body = [
            "formCode" => [
                "systemCode" => $system_code,
                "schemaVersion"=> $schema_version,
                "value" => $value
            ],
            "encryption" => [
                "encryptedSymmetricKey" => $encrypted_symetric_key,
                "initializationVector" => base64_encode($this->iv)
            ]
        ];
        
        $response = $this->ksef_api->start_interactive_session($access_token['access_token'], json_encode($body));
        $this->session_reference_number = $response['referenceNumber'];
        return $response['referenceNumber'];
    }

    public function send($invoice) {
        $access_token = $this->auth->get_access_token();

        $invoice_hash = hash("sha256", $invoice, true);

        $cipher = new AES('cbc');
        $cipher->setKey($this->symetric_key);
        $cipher->setIV($this->iv);
        $cipher->enablePadding();

        $ciphertext = $cipher->encrypt($invoice);

        $body = [
            "invoiceHash" => base64_encode($invoice_hash),
            "invoiceSize" => strlen($invoice),
            "encryptedInvoiceHash" => base64_encode(hash("sha256", $ciphertext, true)),
            "encryptedInvoiceSize" => strlen($ciphertext),
            "encryptedInvoiceContent" => base64_encode($ciphertext)
        ];

        $response = $this->ksef_api->send_invoice($access_token['access_token'], $this->session_reference_number, json_encode($body));
        $this->invoice_reference_number = $response['referenceNumber'];
        return $response['referenceNumber'];
    }

    public function close_session() {
        $access_token = $this->auth->get_access_token();
        $response = $this->ksef_api->close_interactive_session($access_token['access_token'], $this->session_reference_number);
        return $response;
    }
}


