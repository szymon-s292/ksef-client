<?php
require_once 'config.php';

global $db_host, $db_user, $db_pass, $db_name, $conn;
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error)
    die("DB connection failed");