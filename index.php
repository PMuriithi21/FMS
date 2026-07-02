<?php
// index.php  (login page — place in /fms/)
require_once __DIR__ . '/includes/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Already logged in — redirect to correct dashboard
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: /fms/{$role}/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT user_id, name, username, password, role, status FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['name']     = $user['name'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            header("Location: /fms/{$user['role']}/dashboard.php");
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

$urlError = $_GET['error'] ?? '';
if ($urlError === 'unauthorized') $error = 'You do not have permission to access that page.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Login — Fuel Management System</title>
    <link rel="stylesheet" href="/fms/css/style.css"/>
</head>
<body>
<div class="bubble-bg">
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
</div>

<div class="login-page">
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-circle">⛽</div>
            <h1>Fuel Management System</h1>
            <p>Small Petrol Station Operations</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-error">⚠️ <?= clean($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" class="form-control"
                       placeholder="Enter your username"
                       value="<?= clean($_POST['username'] ?? '') ?>"
                       autocomplete="username" required/>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control"
                       placeholder="Enter your password"
                       autocomplete="current-password" required/>
            </div>
            <button type="submit" class="btn btn-primary">🔐 Sign In</button>
        </form>

        <p style="text-align:center;margin-top:20px;font-size:12px;color:var(--text-light);">
            Strathmore University &mdash; IS Project 2025
        </p>
    </div>
</div>
</body>
</html>