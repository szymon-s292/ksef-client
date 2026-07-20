<?php
namespace KSeFClient;

class XadesSigner
{
    private string $raw = '';
    private array $info = [];
    private \OpenSSLAsymmetricKey $privateKey;

    const NAMESPACE_DS = 'http://www.w3.org/2000/09/xmldsig#';
    const NAMESPACE_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    public function sign(string $xml, string $privateKey, string $certificate, string $pkcs12Password): \DOMDocument {
        if(empty($privateKey)) {
            if(!$this->certFromPkcs12($certificate, $pkcs12Password)) {
                throw new \Exception('No valid pkcs12 file provided.');
            }
        } else {
            if(!$this->certFromPemPair($privateKey, $certificate, $pkcs12Password)) {
                throw new \Exception('No valid pem files provided.');
            }
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = false;
        $dom->loadXML($xml);

        $root = $dom->firstChild;
        if ($root !== null) {
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', 
                                  'http://www.w3.org/2000/09/xmldsig#');
            $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', 
                                  'http://uri.etsi.org/01903/v1.3.2#');
        }

        $ids = [];
        $digest1 = base64_encode(hash('sha256', $dom->C14N(), true));

        $signature = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Signature');
        $signature->setAttribute('Id', $ids['signature'] = self::guid());

        $dom->firstChild?->appendChild($signature);

        $signedInfo = $dom->createElementNS(self::NAMESPACE_DS, 'ds:SignedInfo');
        $signedInfo->setAttribute('Id', self::guid());

        $signature->appendChild($signedInfo);

        $canonicalizationMethod = $dom->createElementNS(self::NAMESPACE_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');

        $signedInfo->appendChild($canonicalizationMethod);

        $keyDetails = openssl_pkey_get_details($this->privateKey);
        $keyType = $keyDetails['type'] ?? OPENSSL_KEYTYPE_RSA;
        
        $signatureAlgorithmUri = ($keyType === OPENSSL_KEYTYPE_EC) 
            ? 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256'
            : 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

        $signatureMethod = $dom->createElementNS(self::NAMESPACE_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', $signatureAlgorithmUri);

        $signedInfo->appendChild($signatureMethod);

        $reference1 = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Reference');
        $reference1->setAttribute('Id', self::guid());
        $reference1->setAttribute('URI', '');

        $signedInfo->appendChild($reference1);

        $transforms = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Transforms');

        $reference1->appendChild($transforms);

        $transform = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');

        $transforms->appendChild($transform);

        $digestMethod = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');

        $reference1->appendChild($digestMethod);

        $digestValue = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestValue', preg_replace('/\s+/', '', $digest1));

        $reference1->appendChild($digestValue);

        $reference2 = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Reference');
        $reference2->setAttribute('Id', self::guid());
        $reference2->setAttribute('Type', 'http://uri.etsi.org/01903#SignedProperties');

        $signedInfo->appendChild($reference2);

        $digestMethod2 = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestMethod');
        $digestMethod2->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');

        $reference2->appendChild($digestMethod2);

        $signatureValue = $dom->createElementNS(self::NAMESPACE_DS, 'ds:SignatureValue');
        $signatureValue->setAttribute('Id', self::guid());

        $signature->appendChild($signatureValue);

        $keyInfo = $dom->createElementNS(self::NAMESPACE_DS, 'ds:KeyInfo');

        $signature->appendChild($keyInfo);

        $x509data = $dom->createElementNS(self::NAMESPACE_DS, 'ds:X509Data');

        $keyInfo->appendChild($x509data);

        $x509Certificate = $dom->createElementNS(self::NAMESPACE_DS, 'ds:X509Certificate', $this->raw);

        $x509data->appendChild($x509Certificate);

        $object = $dom->createElementNS(self::NAMESPACE_DS, 'ds:Object');

        $signature->appendChild($object);

        $qualifyingProperties = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Id', self::guid());
        $qualifyingProperties->setAttribute('Target', '#' . $ids['signature']);

        $object->appendChild($qualifyingProperties);

        $signedProperties = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', $ids['signed_properties'] = self::guid());

        $qualifyingProperties->appendChild($signedProperties);

        $reference2->setAttribute('URI', '#' . $ids['signed_properties']);

        $signedSignatureProperties = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:SignedSignatureProperties');

        $signedProperties->appendChild($signedSignatureProperties);

        $signatureTime = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:SigningTime', (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'));

        $signedSignatureProperties->appendChild($signatureTime);

        $signingCertificate = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:SigningCertificate');

        $signedSignatureProperties->appendChild($signingCertificate);

        $xadesCert = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:Cert');

        $signingCertificate->appendChild($xadesCert);

        $xadesCertDigest = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:CertDigest');

        $xadesCert->appendChild($xadesCertDigest);

        $digestMethod3 = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestMethod');
        $digestMethod3->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');

        $xadesCertDigest->appendChild($digestMethod3);

        $digestValue = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestValue', preg_replace('/\s+/', '', $this->getFingerPrint()));

        $xadesCertDigest->appendChild($digestValue);

        $xadesIssuerSerial = $dom->createElementNS(self::NAMESPACE_XADES, 'xades:IssuerSerial');

        $xadesCert->appendChild($xadesIssuerSerial);

        $x509IssuerName = $dom->createElementNS(self::NAMESPACE_DS, 'ds:X509IssuerName', $this->getIssuer());

        $xadesIssuerSerial->appendChild($x509IssuerName);

        $x509SerialNumber = $dom->createElementNS(self::NAMESPACE_DS, 'ds:X509SerialNumber', $this->getSerialNumber());

        $xadesIssuerSerial->appendChild($x509SerialNumber);

        $xmlDigest = base64_encode(hash('sha256', $signedProperties->C14N(), true));

        $digestValue = $dom->createElementNS(self::NAMESPACE_DS, 'ds:DigestValue', preg_replace('/\s+/', '', $xmlDigest));

        $reference2->appendChild($digestValue);

        $currentDigest = '';
        
        $signedInfoC14N = $signedInfo->C14N();

        $signAlgorithm = defined('OPENSSL_ALGO_SHA256') ? OPENSSL_ALGO_SHA256 : 'sha256';
        
        if(openssl_sign($signedInfoC14N, $currentDigest, $this->privateKey, $signAlgorithm) === false) {
            throw new \Exception('Problem with signing an XML document: ' . (openssl_error_string() ?: 'unknown error'));
        }

        $keyDetails = openssl_pkey_get_details($this->privateKey);
        $keyType = $keyDetails['type'] ?? OPENSSL_KEYTYPE_RSA;
        
        if ($keyType === OPENSSL_KEYTYPE_EC) {
            $curveSize = $this->getECDSACurveSize($keyDetails);
            $currentDigest = $this->convertECDSADerToRaw($currentDigest, $curveSize);
        }

        $signatureValue->textContent = preg_replace('/\\s+/', '', base64_encode($currentDigest));

        return $dom;
    }

    private static function guid(): string
    {
        mt_srand((int)(microtime(true) * 10000));
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        return 'ID-' .substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
    }

    private function certFromPkcs12(string $pkcs12, string $password = ''): bool
    {
        $certs = [];
        if((openssl_pkcs12_read($pkcs12, $certs, $password)) === false)
            throw new \Exception(sprintf("Can't read a p12 file. OpenSSL: %s", (openssl_error_string() ?: '')));

        if(($this->privateKey = openssl_pkey_get_private($certs['pkey'], $password)) === false)
            throw new \Exception(sprintf("Can't read a private key. OpenSSL: %s", (openssl_error_string() ?: '')));

        if(($this->info = openssl_x509_parse($certs['cert'])) === false)
            throw new \Exception(sprintf('Unable to parse a cert. OpenSSL: %s', (openssl_error_string() ?: '')));

        $this->raw = $this->extractCertificateBase64($certs['cert']);

        return true;
    }

    private function certFromPemPair(string $pkeyPem, string $certPem, string $password = ''): bool
    {
        $pkeyPem = $this->ensurePemFormat($pkeyPem, 'PRIVATE KEY');

        if(($this->privateKey = openssl_pkey_get_private($pkeyPem, $password ?: null)) === false)
            throw new \Exception(sprintf("Can't read a private key. OpenSSL: %s", (openssl_error_string() ?: '')));

        $this->raw = $this->extractCertificateBase64($certPem);

        if(($this->info = openssl_x509_parse($certPem)) === false)
            throw new \Exception(sprintf('Unable to parse a cert. OpenSSL: %s', (openssl_error_string() ?: '')));

        return true;
    }

    private function getFingerPrint(): string
    {
        return base64_encode(hash('sha256', base64_decode($this->raw), true));
    }

    private function getECDSACurveSize(array $keyDetails): int
    {
        if (!isset($keyDetails['ec'])) {
            return 32;
        }
        
        $curve = $keyDetails['ec'];
        
        $curveMap = [
            'secp256r1' => 32, 'prime256v1' => 32, 'P-256' => 32,
            'secp384r1' => 48, 'P-384' => 48,
            'secp521r1' => 66, 'P-521' => 66,
            'secp256k1' => 32
        ];
        
        foreach ($curveMap as $name => $size) {
            if (stripos(json_encode($curve), $name) !== false) {
                return $size;
            }
        }
        
        return 32;
    }

    private function convertECDSADerToRaw(string $signature, int $componentSize): string
    {
        $len = strlen($signature);
        
        if ($len < 6 || ord($signature[0]) !== 0x30) {
            // Probably already in raw format or invalid
            return $signature;
        }

        try {
            $offset = 2;
            
            if (ord($signature[$offset]) !== 0x02) {
                throw new \Exception('Invalid DER format: R component marker not found');
            }
            $offset++;
            
            $rLength = ord($signature[$offset]);
            $offset++;
            
            if ($offset + $rLength > $len) {
                throw new \Exception('Invalid DER format: R length exceeds signature');
            }
            
            $r = substr($signature, $offset, $rLength);
            $offset += $rLength;
            
            if (ord($signature[$offset]) !== 0x02) {
                throw new \Exception('Invalid DER format: S component marker not found');
            }
            $offset++;
            
            $sLength = ord($signature[$offset]);
            $offset++;
            
            if ($offset + $sLength > $len) {
                throw new \Exception('Invalid DER format: S length exceeds signature');
            }
            
            $s = substr($signature, $offset, $sLength);
            
            $r = str_pad(ltrim($r, "\x00"), $componentSize, "\x00", STR_PAD_LEFT);
            $s = str_pad(ltrim($s, "\x00"), $componentSize, "\x00", STR_PAD_LEFT);
            
            return $r . $s;
        } catch (\Exception $e) {
            return $signature;
        }
    }

    private function ensurePemFormat(string $content, string $keyType): string
    {
        if (strpos($content, "-----BEGIN") !== false && strpos($content, "-----END") !== false) {
            return $content;
        }

        $content = trim(str_replace(["\n", "\r", "\t", " "], "", $content));

        $content = chunk_split($content, 64, "\n");
        return "-----BEGIN $keyType-----\n" . $content . "-----END $keyType-----\n";
    }

    private function extractCertificateBase64(string $certData): string
    {
        $certData = preg_replace('/Bag Attributes[^-]*-----BEGIN/s', '-----BEGIN', $certData);
        
        if (preg_match('/-----BEGIN CERTIFICATE-----\s*([A-Za-z0-9+\/=\s]+?)\s*-----END CERTIFICATE-----/s', $certData, $matches)) {
            return trim(preg_replace('/\s+/', '', $matches[1]));
        }
        
        return trim(preg_replace('/[\s-----BEGIN CERTIFICATE-----END CERTIFICATE-----]/', '', $certData));
    }

    private function getSerialNumber(): string
    {
        return $this->hexdecBig($this->info['serialNumberHex']);
    }

    private function getIssuer(): string
    {
        $issuer = [];
        foreach ($this->info['issuer'] as $key => $value) {
            $issuer[] = $key . '=' . $value;
        }
        return implode(', ', array_reverse($issuer));
    }

    private function hexdecBig(string $hex): string
    {
        $hex = strtolower($hex);
        if (str_starts_with($hex, "0x")) {
            $hex = substr($hex, 2);
        }

        $dec = '0';
        $len = strlen($hex);
        for ($i = 0; $i < $len; $i++) {
            $current = strpos('0123456789abcdef', $hex[$i]);
            if ($current === false) {
                throw new \InvalidArgumentException("Invalid hex string: $hex");
            }
            $dec = bcmul($dec, '16');
            $dec = bcadd($dec, (string)$current);
        }

        return $dec;
    }
}