<?php
// includes/header.php
// Call: include after requireRole() in each page
// Expects: $pageTitle, $activeNav
$user = currentUser();
$initials = strtoupper(substr($user['name'] ?? 'U', 0, 1));
$role = $user['role'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?= clean($pageTitle ?? 'FMS') ?> — <?= SITE_NAME ?></title>
    <link rel="stylesheet" href="/fms/css/style.css"/>
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
        <p><?= clean($user['name'] ?? '') ?></p>
        <span class="sidebar-role"><?= ucfirst($role) ?></span>
    </div>
    <nav>
        <?php if ($role === 'admin'): ?>
        <div class="nav-section">Main</div>
        <a href="/fms/admin/dashboard.php"  class="nav-link <?= ($activeNav==='dashboard') ?'active':'' ?>"><span class="nav-icon">📊</span> Dashboard</a>
        <div class="nav-section">Management</div>
        <a href="/fms/admin/users.php"      class="nav-link <?= ($activeNav==='users') ?'active':'' ?>"><span class="nav-icon">👥</span> User Accounts</a>
        <a href="/fms/admin/fuel_types.php" class="nav-link <?= ($activeNav==='fuel') ?'active':'' ?>"><span class="nav-icon">🛢</span> Fuel Types</a>
        <a href="/fms/admin/deliveries.php" class="nav-link <?= ($activeNav==='deliveries') ?'active':'' ?>"><span class="nav-icon">🚛</span> Deliveries</a>
        <a href="/fms/admin/stock.php"      class="nav-link <?= ($activeNav==='stock') ?'active':'' ?>"><span class="nav-icon">📦</span> Stock Levels</a>
        <div class="nav-section">Reports</div>
        <a href="/fms/admin/reports.php"    class="nav-link <?= ($activeNav==='reports') ?'active':'' ?>"><span class="nav-icon">📋</span> All Reports</a>
        <a href="/fms/admin/audit.php"      class="nav-link <?= ($activeNav==='audit') ?'active':'' ?>"><span class="nav-icon">🔍</span> Audit Trail</a>

        <?php elseif ($role === 'supervisor'): ?>
        <div class="nav-section">My Shift</div>
        <a href="/fms/supervisor/dashboard.php" class="nav-link <?= ($activeNav==='dashboard') ?'active':'' ?>"><span class="nav-icon">📊</span> Dashboard</a>
        <a href="/fms/supervisor/shift.php"     class="nav-link <?= ($activeNav==='shift') ?'active':'' ?>"><span class="nav-icon">⏱</span> Shift Entry</a>
        <a href="/fms/supervisor/delivery.php"  class="nav-link <?= ($activeNav==='delivery') ?'active':'' ?>"><span class="nav-icon">🚛</span> Log Delivery</a>
        <div class="nav-section">View</div>
        <a href="/fms/supervisor/stock.php"     class="nav-link <?= ($activeNav==='stock') ?'active':'' ?>"><span class="nav-icon">📦</span> Stock Levels</a>
        <a href="/fms/supervisor/history.php"   class="nav-link <?= ($activeNav==='history') ?'active':'' ?>"><span class="nav-icon">📋</span> My Shift History</a>

        <?php elseif ($role === 'manager'): ?>
        <div class="nav-section">Overview</div>
        <a href="/fms/manager/dashboard.php"   class="nav-link <?= ($activeNav==='dashboard') ?'active':'' ?>"><span class="nav-icon">📊</span> Dashboard</a>
        <a href="/fms/manager/stock.php"       class="nav-link <?= ($activeNav==='stock') ?'active':'' ?>"><span class="nav-icon">📦</span> Stock Status</a>
        <div class="nav-section">Reports</div>
        <a href="/fms/manager/daily.php"       class="nav-link <?= ($activeNav==='daily') ?'active':'' ?>"><span class="nav-icon">📅</span> Daily Reports</a>
        <a href="/fms/manager/monthly.php"     class="nav-link <?= ($activeNav==='monthly') ?'active':'' ?>"><span class="nav-icon">📆</span> Monthly Reports</a>
        <a href="/fms/manager/deliveries.php"  class="nav-link <?= ($activeNav==='deliveries') ?'active':'' ?>"><span class="nav-icon">🚛</span> Delivery Log</a>
        <a href="/fms/manager/payroll.php"     class="nav-link <?= ($activeNav==='payroll') ?'active':'' ?>"><span class="nav-icon">💰</span> Payroll Report</a>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="/fms/logout.php" class="btn btn-nude btn-sm btn-block">🚪 Log Out</a>
    </div>
</aside>
<main class="main-content">
    <div class="topbar">
        <span class="topbar-title"><?= clean($pageTitle ?? '') ?></span>
        <div class="topbar-user">
            <span style="font-size:13px;color:var(--text-light);"><?= date('D, d M Y') ?></span>
            <div class="user-avatar"><?= $initials ?></div>
            <span style="font-size:14px;font-weight:600;color:var(--navy);"><?= clean($user['name'] ?? '') ?></span>
        </div>
    </div>
    <div class="content-body">