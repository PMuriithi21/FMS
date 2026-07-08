<?php

require_once __DIR__ . '/../vendor/autoload.php';

use AfricasTalking\SDK\AfricasTalking;

function sendSMS($phone, $message)
{
    $username = "sandbox";
    $apiKey = "atsk_e767f6340a41b9f0d1da195dd03cc302da4a0150e085598b3f3068bfb23653dacfd0a431";

    try {

        $AT = new AfricasTalking($username, $apiKey);

        $sms = $AT->sms();

        $result = $sms->send([
            'to'      => $phone,
            'message' => $message
        ]);

        return $result;

    } catch(Exception $e) {

        error_log("SMS Error: ".$e->getMessage());

        return false;

    }
}