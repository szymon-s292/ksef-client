<?php
enum KsefMode: string {
    case TEST = 'https://api-test.ksef.mf.gov.pl';
    case DEMO = 'https://api-demo.ksef.mf.gov.pl';
    case PROD = 'https://api.ksef.mf.gov.pl';
}

function get_ksef_mode($mode) {
    return match ($mode) {
        'TEST' => KsefMode::TEST,
        'DEMO' => KsefMode::DEMO,
        'PROD' => KsefMode::PROD,
        default => throw new InvalidArgumentException("Nieprawidłowy tryb KSeF: $mode"),
    };
}
