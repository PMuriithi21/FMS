<?php
// includes/config.php
// ── Database connection ───────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // XAMPP default
define('DB_PASS', '');           // XAMPP default — no password
define('DB_NAME', 'fms_db');
define('SITE_NAME', 'Fuel Management System');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('<div style="font-family:Arial;padding:30px;background:#1a1a2e;color:#e8d5b7;min-height:100vh;">
                <h2>Database Connection Failed</h2>
                <p>Make sure XAMPP is running and the database <strong>fms_db</strong> exists.</p>
                <p>Error: ' . $conn->connect_error . '</p>
                <p><a href="#" style="color:#c9a87c;">Setup Instructions</a></p>
            </div>');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

// ── Session helpers ───────────────────────────────────────────
function requireLogin() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /fms/index.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        header('Location: /fms/index.php?error=unauthorized');
        exit;
    }
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
}

function currentUser() {
    return $_SESSION ?? [];
}

// ── Sanitise input ────────────────────────────────────────────
function clean($val) {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}