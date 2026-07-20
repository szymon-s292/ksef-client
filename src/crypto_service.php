<?php
namespace KSeFClient;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/key_manager.php';

use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\PublicKeyLoader;

class CryptoService {
    private $key_manager;

    public function __construct(KsefMode $mode) {
        $this->key_manager = new KeyManager($mode);
    }

    public function symetric_key_encryption($symmetric_key) {
        $ksef_symmetric_key_encryption_key = $this->key_manager->get_symetric_encryption_key();

        try {
            $rsa = PublicKeyLoader::load($ksef_symmetric_key_encryption_key)
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
            
            $ciphertext = $rsa->encrypt($symmetric_key);

            return base64_encode($ciphertext);
        } catch(\Throwable $e) {
            throw new \Exception('Encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function ksef_token_encryption($plaintext) {
        $ksef_token_encryption_key = $this->key_manager->get_ksef_token_encryption_key();
        
        try {
            $rsa = PublicKeyLoader::load($ksef_token_encryption_key)
                ->withPadding(RSA::ENCRYPTION_OAEP)
                ->withHash('sha256')
                ->withMGFHash('sha256');
            
            $ciphertext = $rsa->encrypt($plaintext);

            return base64_encode($ciphertext);
        } catch(\Throwable $e) {
            throw new \Exception('Encryption failed: ' . $e->getMessage(), 0, $e);
        }
    }
}