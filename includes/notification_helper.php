<?php
require_once __DIR__ . '/config.php';

function createNotification(
    $title,
    $message,
    $role,
    $notificationType,
    $style = "info"
)
{
    $db = getDB();

    $stmt = $db->prepare("
        INSERT INTO notifications
        (title, message, recipient_role, type, notification_type)
        VALUES (?,?,?,?,?)
    ");

    $stmt->bind_param(
        "sssss",
        $title,
        $message,
        $role,
        $style,
        $notificationType
    );

    $stmt->execute();
    $stmt->close();
}

function markNotificationAsRead($notificationId)
{
    $db = getDB();

    $stmt = $db->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = ?
    ");

    $stmt->bind_param("i", $notificationId);
    $stmt->execute();
    $stmt->close();
}