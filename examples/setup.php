<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;
use KSeFClient\InteractiveSession;

$ksef_cert = file_get_contents(__DIR__ . "/1091041978.crt");
$ksef_pkey = file_get_contents(__DIR__ . "/1091041978.key");

$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, "", true);
$tokens = $auth->auth_with_xades();

$interactive_session = new InteractiveSession($auth, KsefMode::TEST);
$reference_number = $interactive_session->start_session("FA (3)", "1-0E", "FA");

print_r($reference_number);