<?php
require_once __DIR__ . '/config.php';

function createNotification($title, $message, $role, $type = 'info')
{
    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO notifications
        (title, message, recipient_role, type)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "ssss",
        $title,
        $message,
        $role,
        $type
    );

    $stmt->execute();
    $stmt->close();
}