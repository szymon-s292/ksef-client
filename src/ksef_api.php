<?php 
namespace KSeFClient;

require_once __DIR__ . "/http.php";

class BadRequestException extends \Exception {
    public $exceptionDetails;
    public $exceptionCode;

    public function __construct($exceptionCode = null, $exceptionDetails = null) {
        parent::__construct("Bad request");

        $this->exceptionCode = $exceptionCode;
        $this->exceptionDetails = $exceptionDetails;
    }
}

class TooManyRequestsException extends \Exception {
    public function __construct() {
        parent::__construct("Too many requests");
    }
}

class KsefApi {
    private string $url;

    public function __construct(KsefMode $mode) {
        $this->url = $mode->value;
    }

    public function get_challenge() {
        $response = Http::post($this->url."/v2/auth/challenge");

        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/auth/challenge responded with status code: ".$response->statusCode);
        }

        $json = json_decode($response->body, true);
        return $json;
    }

    public function submit_ksef_token(string $encrypted_token) {
        $response = Http::post($this->url."/v2/auth/ksef-token", $encrypted_token, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }
        
        if($response->statusCode != 202) {
            throw new \Exception($this->url."/v2/auth/ksef-token responded with status code: ".$response->statusCode . " " . $response->body);
        }

        $json = json_decode($response->body, true);
        return $json;
    }

    public function submit_xades(string $xades, bool $verifyCertificateChain = true) {
        $response = Http::post($this->url."/v2/auth/xades-signature?verifyCertificateChain=".($verifyCertificateChain ? "true" : "false"), $xades, [
            'Content-Type: application/xml',
            'Accept: application/json'
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }

        if($response->statusCode != 202) {
            throw new \Exception($this->url."/v2/auth/xades-signature responded with status code: " . $response->statusCode . " " . $response->body);
        }

        $json = json_decode($response->body, true);
        return $json;
    }

    public function get_authentication_status($reference_number, $authentication_token) {
        $response = Http::get($this->url."/v2/auth/".$reference_number, [
            "Authorization: Bearer $authentication_token",
            "Accept: application/json"]);
        
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/auth/$reference_number responded with status code: ".$response->statusCode . " " . $response->body);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }

    public function get_access_tokens($authentication_token) {
        $response = Http::post($this->url."/v2/auth/token/redeem", null, [
            "Authorization: Bearer $authentication_token",
            "Accept: application/json",
            "Content-Length: 0"
        ]);
        
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/auth/token/redeem responded with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }

    public function refresh_access_token($refresh_token) {
        $response = Http::post($this->url."/v2/auth/token/refresh", null, [
            "Authorization: Bearer $refresh_token",
            "Accept: application/json",
            "Content-Length: 0"
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }

        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/auth/token/refresh responded with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }

    public function get_public_keys() {
        $response = Http::get($this->url."/v2/security/public-key-certificates", [
            "Accept: application/json",
        ]);
        
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/security/public-key-certificates with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }

    public function start_interactive_session($access_token, $body) {
        $response = Http::post($this->url."/v2/sessions/online", $body, [
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if($response->statusCode != 201) {
            throw new \Exception($this->url."/v2/sessions/online responded with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }

    public function send_invoice($access_token, $reference_number, $body) {
        $response = Http::post($this->url."/v2/sessions/online/$reference_number/invoices", $body, [
            'Authorization: Bearer '.$access_token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        $json = json_decode($response->body, true);

        if($response->statusCode != 202) {
            throw new \Exception($this->url."/v2/sessions/online/$reference_number/invoices responded with status code: ".$response->statusCode);
        }
        
        return $json;
    }

    public function close_interactive_session($access_token, $reference_number) {
        $response = Http::post($this->url."/v2/sessions/online/$reference_number/close", null, [
            'Authorization: Bearer '.$access_token,
            'Accept: application/json',
            'Content-Length: 0'
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }

        if($response->statusCode != 204) {
            throw new \Exception($this->url."/v2/sessions/online/$reference_number/close responded with status code: ".$response->statusCode);
        }
        
        return true;
    }

    public function get_session_invoices($access_token, $reference_number) {
        $response = Http::get($this->url."/v2/sessions/$reference_number/invoices", [
            'Authorization: Bearer '.$access_token,
            'Accept: application/json',
        ]);
    
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/sessions/$reference_number/invoices responded with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    }    

    public function get_invoice_from_session($access_token, $session_reference_number, $invoice_reference_number) {
        $response = Http::get($this->url."/v2/sessions/$session_reference_number/invoices/$invoice_reference_number", [
            'Authorization: Bearer '.$access_token,
            'Accept: application/json',
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }
    
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/sessions/$session_reference_number/invoices responded with status code: ".$response->statusCode);
        }
        
        $json = json_decode($response->body, true);
        return $json;
    } 

    public function download_upo($url) {
        $response = Http::get($url, [
            'Accept: application/xml',
        ]);
    
        if($response->statusCode != 200) {
            throw new \Exception($url." responded with status code: ".$response->statusCode);
        }
        
        return $response->body;
    }

    public function get_invoice_metadata_list($access_token, $body, $pageOffset = 0, $pageSize = 10, $sortOrder = "Desc") {
        $response = Http::post($this->url."/v2/invoices/query/metadata?pageOffset=$pageOffset&pageSize=$pageSize&sortOrder=$sortOrder", $body, [
            'Authorization: Bearer '.$access_token,
            'Accept: application/json',
            'Content-Type: application/json'
        ]);

        if($response->statusCode == 400) {
            $json = json_decode($response->body, true);
            $exception = $json['exception'];
            $exceptionData = $exception['exceptionDetailList'][0];

            throw new BadRequestException($exceptionData['exceptionCode'], $exceptionData['exceptionDescription']);
        }

        if($response->statusCode == 429) {
            throw new TooManyRequestsException();
        }

        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/invoices/query/metadata responded with status code: ".$response->statusCode);
        }

        $json = json_decode($response->body, true);
        return $json;
    } 

    public function get_invoice_by_ksef_nr($access_token, $ksef_nr) {
        $response = Http::get($this->url."/v2/invoices/ksef/$ksef_nr", [
            'Authorization: Bearer '.$access_token,
            'Accept: application/xml',
        ]);
    
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/invoices/ksef/$ksef_nr responded with status code: ".$response->statusCode);
        }
        
        return $response->body;
    }

    public function get_upo_by_reference_numbers($access_token, $session_reference_number, $invoice_reference_number) {
        $response = Http::get($this->url . "/v2/sessions/$session_reference_number/invoices/$invoice_reference_number/upo", [
            'Authorization: Bearer '.$access_token,
            'Accept: application/xml',
        ]);
    
        if($response->statusCode != 200) {
            throw new \Exception($this->url."/v2/sessions/$session_reference_number/invoices/$invoice_reference_number/upo responded with status code: ".$response->statusCode);
        }
        
        return $response->body;
    }
}
