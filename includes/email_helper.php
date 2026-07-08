<?php
// includes/email_helper.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendReportEmail($toEmail, $toName, $subject, $bodyHtml, $attachmentPath, $attachmentName) {

    $mail = new PHPMailer(true);

    try {
        // ---- SMTP CONFIG (Gmail example) ----
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'peris.muriithi@strathmore.edu';       // <-- change this
        $mail->Password   = 'rlwq ntpk zyzm lqca';     // <-- Gmail App Password, NOT your normal password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('peris.muriithi@strathmore.edu', 'Fuel Management System');
        $mail->addAddress($toEmail, $toName);

        if ($attachmentPath && file_exists($attachmentPath)) {
            $mail->addAttachment($attachmentPath, $attachmentName);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Email failed: " . $mail->ErrorInfo);
        return false;
    }
}