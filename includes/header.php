<?php
// includes/header.php
// Call: include after requireRole() in each page
// Expects: $pageTitle, $activeNav

$user = currentUser();
$db = getDB();

// Count unread notifications
$stmt = $db->prepare("
    SELECT COUNT(*) AS total
    FROM notifications
    WHERE recipient_role = ?
    AND is_read = 0
");
$stmt->bind_param("s", $user['role']);
$stmt->execute();
$count = $stmt->get_result()->fetch_assoc();
$notificationCount = $count['total'];
$stmt->close();

// Get latest notifications
$stmt = $db->prepare("
    SELECT *
    FROM notifications
    WHERE recipient_role = ?
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("s", $user['role']);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

$initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$role = $user['role'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= clean($pageTitle ?? 'FMS') ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/fms/css/style.css">
</head>

<body>

<div class="bubble-bg">
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
    <div class="bubble"></div><div class="bubble"></div>
</div>

<div class="dashboard-layout page-wrap">

<aside class="sidebar">

    <div class="sidebar-header">
        <div class="logo-circle">⛽</div>

        <h2>Fuel Management<br>System</h2>

        <p><?= clean($user['name']) ?></p>

        <span class="sidebar-role">
            <?= ucfirst($role) ?>
        </span>
    </div>

    <nav>

        <?php if($role=="admin"): ?>

            <div class="nav-section">Main</div>

            <a href="/fms/admin/dashboard.php" class="nav-link <?= ($activeNav=="dashboard")?"active":"" ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <div class="nav-section">Management</div>

            <a href="/fms/admin/users.php" class="nav-link <?= ($activeNav=="users")?"active":"" ?>">
                <span class="nav-icon">👥</span> User Accounts
            </a>

            <a href="/fms/admin/fuel_types.php" class="nav-link <?= ($activeNav=="fuel")?"active":"" ?>">
                <span class="nav-icon">⛽</span> Fuel Types
            </a>

            <a href="/fms/admin/deliveries.php" class="nav-link <?= ($activeNav=="deliveries")?"active":"" ?>">
                <span class="nav-icon">🚛</span> Deliveries
            </a>

            <a href="/fms/admin/stock.php" class="nav-link <?= ($activeNav=="stock")?"active":"" ?>">
                <span class="nav-icon">📦</span> Stock Levels
            </a>

            <div class="nav-section">Reports</div>

            <a href="/fms/admin/reports.php" class="nav-link <?= ($activeNav=="reports")?"active":"" ?>">
                <span class="nav-icon">📋</span> Reports
            </a>

            <a href="/fms/admin/audit.php" class="nav-link <?= ($activeNav=="audit")?"active":"" ?>">
                <span class="nav-icon">🔍</span> Audit Trail
            </a>

        <?php elseif($role=="supervisor"): ?>

            <div class="nav-section">Supervisor</div>

            <a href="/fms/supervisor/dashboard.php" class="nav-link <?= ($activeNav=="dashboard")?"active":"" ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <a href="/fms/supervisor/shift.php" class="nav-link <?= ($activeNav=="shift")?"active":"" ?>">
                <span class="nav-icon">⏱</span> Shift Entry
            </a>

            <a href="/fms/supervisor/delivery.php" class="nav-link <?= ($activeNav=="delivery")?"active":"" ?>">
                <span class="nav-icon">🚛</span> Log Delivery
            </a>

            <a href="/fms/supervisor/stock.php" class="nav-link <?= ($activeNav=="stock")?"active":"" ?>">
                <span class="nav-icon">📦</span> Stock
            </a>

            <a href="/fms/supervisor/history.php" class="nav-link <?= ($activeNav=="history")?"active":"" ?>">
                <span class="nav-icon">📋</span> History
            </a>

        <?php elseif($role=="manager"): ?>

            <div class="nav-section">Manager</div>

            <a href="/fms/manager/dashboard.php" class="nav-link <?= ($activeNav=="dashboard")?"active":"" ?>">
                <span class="nav-icon">📊</span> Dashboard
            </a>

            <a href="/fms/manager/stock.php" class="nav-link <?= ($activeNav=="stock")?"active":"" ?>">
                <span class="nav-icon">📦</span> Stock
            </a>

            <a href="/fms/manager/daily.php" class="nav-link <?= ($activeNav=="daily")?"active":"" ?>">
                <span class="nav-icon">📅</span> Daily Report
            </a>

            <a href="/fms/manager/monthly.php" class="nav-link <?= ($activeNav=="monthly")?"active":"" ?>">
                <span class="nav-icon">📆</span> Monthly Report
            </a>

            <a href="/fms/manager/deliveries.php" class="nav-link <?= ($activeNav=="deliveries")?"active":"" ?>">
                <span class="nav-icon">🚛</span> Deliveries
            </a>

            <a href="/fms/manager/payroll.php" class="nav-link <?= ($activeNav=="payroll")?"active":"" ?>">
                <span class="nav-icon">💰</span> Payroll
            </a>

        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a href="/fms/logout.php" class="btn btn-nude btn-sm btn-block">
            🚪 Log Out
        </a>
    </div>

</aside>

<main class="main-content">

<div class="topbar">

    <span class="topbar-title">
        <?= clean($pageTitle ?? '') ?>
    </span>

    <div class="topbar-user">

        <div class="notification-wrapper">

            <button type="button" class="notification-btn">
                🔔

                <?php if($notificationCount>0): ?>

                    <span class="notification-count">
                        <?= $notificationCount ?>
                    </span>

                <?php endif; ?>

            </button>

            <div class="notification-dropdown">

                <h4>🔔 Notifications</h4>

                <?php if($notifications->num_rows==0): ?>

                    <div class="notification-item">
                        No notifications.
                    </div>

                <?php else: ?>

                    <?php while($note=$notifications->fetch_assoc()): ?>

<?php
$link = "#";

switch ($note['notification_type']) {

    case "delivery":
        $link = ($role == "admin")
            ? "/fms/admin/deliveries.php"
            : "/fms/manager/deliveries.php";
        break;

    case "low_stock":
        $link = ($role == "admin")
            ? "/fms/admin/stock.php"
            : "/fms/manager/stock.php";
        break;

    case "user":
        $link = "/fms/admin/users.php";
        break;

    case "report":
        $link = ($role == "admin")
            ? "/fms/admin/reports.php"
            : "/fms/manager/daily.php";
        break;

    default:
        $link = "#";
}
?>

<a href="<?= $link ?>?read=<?= $note['notification_id'] ?>" class="notification-item">

    <strong><?= clean($note['title']) ?></strong>

    <small><?= clean($note['message']) ?></small>

<?php

$time = strtotime($note['created_at']);
$diff = time() - $time;

if ($diff < 60) {
    $ago = "Just now";
} elseif ($diff < 3600) {
    $ago = floor($diff / 60) . " min ago";
} elseif ($diff < 86400) {
    $ago = floor($diff / 3600) . " hr ago";
} else {
    $ago = date("d M Y", $time);
}

?>

    <div class="notification-time">
        🕒 <?= $ago ?>
    </div>

</a>

<?php endwhile; ?>

                <?php endif; ?>

            </div>

        </div>

        <span style="font-size:13px;color:var(--text-light);">
            <?= date('D, d M Y') ?>
        </span>

        <div class="user-avatar">
            <?= $initials ?>
        </div>

        <span style="font-weight:600;color:var(--navy);">
            <?= clean($user['name']) ?>
        </span>

    </div>

</div>

<div class="content-body">