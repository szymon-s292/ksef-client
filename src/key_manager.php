<?php
require_once "http.php";
require_once "ksef_api.php";

class KeyManager {
    private $ksef_api;

    public function __construct(KsefMode $mode) {
        $this->ksef_api = new KsefApi($mode);
    }

    private function get_public_keys() {
        return $this->ksef_api->get_public_keys();
    }

    public function get_ksef_token_encryption_key() {
        $certificates = $this->get_public_keys();

        foreach ($certificates as $item) {
            if (isset($item['usage']) && in_array('KsefTokenEncryption', $item['usage'])) {
                return $item['certificate'] ?? null;
            }
        }
        return null;
    }

    public function get_symetric_encryption_key() {
        $certificates = $this->get_public_keys();

        foreach ($certificates as $item) {
            if (isset($item['usage']) && in_array('SymmetricKeyEncryption', $item['usage'])) {
                return $item['certificate'] ?? null;
            }
        }
        return null;
    }
}