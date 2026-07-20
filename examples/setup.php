<?php
require_once __DIR__ . '/../vendor/autoload.php';

use KSeFClient\KsefMode;
use KSeFClient\Auth;
use KSeFClient\InteractiveSession;

$ksef_cert = file_get_contents(__DIR__ . "/1091041978.crt");
$ksef_pkey = file_get_contents(__DIR__ . "/1091041978.key");

$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, "", true);
$tokens = $auth->auth_with_xades();
