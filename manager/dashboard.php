<?php
// manager/dashboard.php
require_once __DIR__ . '/../includes/config.php';
requireRole('manager');
$db    = getDB();
$today = date('Y-m-d');
$month = date('Y-m');
$pageTitle = 'Manager Dashboard';
$activeNav = 'dashboard';

// Today's totals
$r1 = $db->query("SELECT COALESCE(SUM(volume_sold),0) as vol, COALESCE(SUM(amount_paid),0) as rev FROM transactions WHERE trans_date='$today'");
$todayTotals = $r1->fetch_assoc();

// Monthly totals
$r2 = $db->query("SELECT COALESCE(SUM(volume_sold),0) as vol, COALESCE(SUM(amount_paid),0) as rev FROM transactions WHERE DATE_FORMAT(trans_date,'%Y-%m')='$month'");
$monthTotals = $r2->fetch_assoc();

// Active supervisors today
$r3 = $db->query("SELECT COUNT(DISTINCT user_id) as cnt FROM shifts WHERE shift_date='$today'");
$activeSups = $r3->fetch_assoc()['cnt'];

// Total deliveries this month
$r4 = $db->query("SELECT COUNT(*) as cnt, COALESCE(SUM(volume_received),0) as vol FROM deliveries WHERE DATE_FORMAT(delivery_date,'%Y-%m')='$month'");
$monthDeliveries = $r4->fetch_assoc();

// Stock
$stock = $db->query("SELECT f.fuel_name, COALESCE(s.current_volume,0) as vol FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active'");

// Recent transactions
$recent = $db->query("SELECT t.*, f.fuel_name, u.name as sup_name FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id JOIN users u ON t.user_id=u.user_id ORDER BY t.created_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
    <div style="text-align:center;">
        <div class="stat-bubble navy">
            <span class="stat-icon">📅</span>
            <span class="stat-value"><?= number_format($todayTotals['vol'],0) ?>L</span>
            <span class="stat-label">Sold Today</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble brown">
            <span class="stat-icon">💰</span>
            <span class="stat-value">KES <?= number_format($todayTotals['rev'],0) ?></span>
            <span class="stat-label">Revenue Today</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble nude">
            <span class="stat-icon">📆</span>
            <span class="stat-value"><?= number_format($monthTotals['vol'],0) ?>L</span>
            <span class="stat-label">Sold This Month</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble success">
            <span class="stat-icon">👷</span>
            <span class="stat-value"><?= $activeSups ?></span>
            <span class="stat-label">Supervisors Today</span>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Stock -->
    <div class="card">
        <div class="card-title">📦 Stock Levels</div>
        <?php while($row = $stock->fetch_assoc()):
            $low = $row['vol'] < 500;
        ?>
        <div style="padding:14px 0;border-bottom:1px solid var(--nude-light);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-weight:600;color:var(--navy);">⛽ <?= clean($row['fuel_name']) ?></span>
                <span style="font-size:18px;font-weight:800;color:<?= $low?'var(--danger)':'var(--brown)' ?>;"><?= number_format($row['vol'],2) ?> L</span>
            </div>
            <div style="height:6px;background:var(--nude-light);border-radius:4px;overflow:hidden;">
                <div style="height:100%;width:<?= min(100, ($row['vol']/10000)*100) ?>%;background:<?= $low?'var(--danger)':'linear-gradient(90deg,var(--navy),var(--brown))' ?>;border-radius:4px;transition:width 0.4s;"></div>
            </div>
            <?php if($low): ?><p style="color:var(--danger);font-size:11px;margin-top:4px;">⚠️ Low stock — consider restocking</p><?php endif; ?>
        </div>
        <?php endwhile; ?>
        <div style="margin-top:16px;">
            <a href="/fms/manager/deliveries.php" class="btn btn-brown btn-sm">View Delivery Log →</a>
        </div>
    </div>

    <!-- Quick reports -->
    <div class="card">
        <div class="card-title">📊 Quick Reports</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="/fms/manager/daily.php"      class="btn btn-primary">📅 Daily Sales Report</a>
            <a href="/fms/manager/monthly.php"    class="btn btn-brown">📆 Monthly Sales Report</a>
            <a href="/fms/manager/deliveries.php" class="btn btn-nude">🚛 Delivery Log</a>
            <a href="/fms/manager/payroll.php"    class="btn btn-nude">💰 Supervisor Payroll</a>
            <a href="/fms/manager/stock.php"      class="btn btn-nude">📦 Stock Status</a>
        </div>
        <div style="margin-top:20px;padding:14px;background:var(--nude-pale);border-radius:8px;">
            <div style="font-size:12px;color:var(--text-light);margin-bottom:4px;">This Month's Deliveries</div>
            <div style="font-size:20px;font-weight:800;color:var(--navy);"><?= $monthDeliveries['cnt'] ?> deliveries</div>
            <div style="font-size:14px;color:var(--brown);"><?= number_format($monthDeliveries['vol'],2) ?> L received</div>
        </div>
    </div>
</div>

<!-- Recent transactions -->
<div class="card">
    <div class="card-title">🧾 Recent Transactions</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>Supervisor</th><th>Fuel</th><th>Opening Meter</th><th>Closing Meter</th><th>Volume Sold (L)</th><th>Amount (KES)</th></tr>
            </thead>
            <tbody>
                <?php if ($recent->num_rows === 0): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px;">No transactions yet.</td></tr>
                <?php endif; ?>
                <?php while($row = $recent->fetch_assoc()): ?>
                <tr>
                    <td><?= clean($row['trans_date']) ?></td>
                    <td>👷 <?= clean($row['sup_name']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['opening_meter'],2) ?></td>
                    <td><?= number_format($row['closing_meter'],2) ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= number_format($row['volume_sold'],2) ?></td>
                    <td style="font-weight:700;color:var(--navy);"><?= number_format($row['amount_paid'],2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>