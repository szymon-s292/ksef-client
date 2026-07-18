<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/misc.php';
require_once __DIR__ . '/../src/ksef_api.php';
require_once __DIR__ . '/../vendor/autoload.php';

$ksef_cert = file_get_contents(__DIR__ . "/1091041978.crt");
$ksef_pkey = file_get_contents(__DIR__ . "/1091041978.key");

$auth = new Auth("1091041978", KsefMode::TEST, null, $ksef_cert, $ksef_pkey, "", false);
$tokens = $auth->get_access_token();

echo "<pre>";
print_r($tokens);
echo "</pre>";