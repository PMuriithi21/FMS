<?php
require_once __DIR__ . '/includes/config.php';
requireLogin();

$db = getDB();

$id = (int)($_GET['id'] ?? 0);

$stmt = $db->prepare("
DELETE FROM notifications
WHERE notification_id=?
");

$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;