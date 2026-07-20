<?php
namespace KSeFClient;

class AuthTokenRequestBuilder {
    private \DOMDocument $doc;
    private \DOMElement $root;

    public function __construct() {
        $this->doc = new \DOMDocument('1.0', 'utf-8');
        $this->doc->formatOutput = true;

        $this->root = $this->doc->createElementNS(
            'http://ksef.mf.gov.pl/auth/token/2.0',
            'AuthTokenRequest'
        );
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $this->root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');

        $this->doc->appendChild($this->root);
    }

    public function with_challenge(string $challenge): self {
        $this->root->appendChild($this->doc->createElement('Challenge', $challenge));
        return $this;
    }

    public function with_nip(string $nip): self {
        $ctx = $this->doc->createElement('ContextIdentifier');
        $ctx->appendChild($this->doc->createElement('Nip', $nip));
        $this->root->appendChild($ctx);
        return $this;
    }

    public function with_subject_identifier(): self {
        $this->root->appendChild($this->doc->createElement('SubjectIdentifierType', 'certificateSubject'));
        return $this;
    }

    public function add_section(string $name, array $elements): self {
        $section = $this->doc->createElement($name);
        foreach ($elements as $key => $value) {
            $section->appendChild($this->doc->createElement($key, $value));
        }
        $this->root->appendChild($section);
        return $this;
    }

    public function build(): string {
        return $this->doc->saveXML();
    }
}