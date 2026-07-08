<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require __DIR__ . '/vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

$username = "sandbox";
$apiKey = "PASTE_YOUR_FULL_API_KEY_HERE";

try {

    $AT = new AfricasTalking($username, $apiKey);

    $application = $AT->application();

    $response = $application->fetchApplicationData();

    echo "<pre>";
    print_r($response);
    echo "</pre>";

} catch (Exception $e) {

    echo "<h2>Error</h2>";
    echo $e->getMessage();

}