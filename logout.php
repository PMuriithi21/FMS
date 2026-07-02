<?php
// logout.php
require_once __DIR__ . '/includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
header('Location: /fms/index.php');
exit;