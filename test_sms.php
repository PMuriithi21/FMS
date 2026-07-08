<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "includes/config.php";
require_once "includes/sms_helper.php";

echo "Config loaded.<br>";

$phone = "+254796040314"; // Your number
$message = "Testing SMS";

$result = sendSMS($phone, $message);

echo "<pre>";
var_dump($result);
echo "</pre>";