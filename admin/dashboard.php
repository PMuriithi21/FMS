<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();
$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';
$today = date('Y-m-d');
$month = date('Y-m');
[$year,$mon] = explode('-',$month);

$r1 = $db->query("SELECT COUNT(*) as cnt FROM users WHERE status='active'")->fetch_assoc();
$r2 = $db->query("SELECT COUNT(*) as cnt FROM shifts WHERE shift_date='$today'")->fetch_assoc();
$r3 = $db->query("SELECT COALESCE(SUM(amount_paid),0) as rev FROM transactions WHERE trans_date='$today'")->fetch_assoc();
$r4 = $db->query("SELECT COALESCE(SUM(volume_sold),0) as vol FROM transactions WHERE trans_date='$today'")->fetch_assoc();
$stock = $db->query("SELECT f.fuel_name, COALESCE(s.current_volume,0) as vol FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active'");
$recentTrans = $db->query("SELECT t.*, f.fuel_name, u.name FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id JOIN users u ON t.user_id=u.user_id ORDER BY t.created_at DESC LIMIT 8");

include __DIR__ . '/../includes/header.php';
?>
<div class="stats-grid">
    <div style="text-align:center;"><div class="stat-bubble navy"><span class="stat-icon">👥</span><span class="stat-value"><?= $r1['cnt'] ?></span><span class="stat-label">Active Users</span></div></div>
    <div style="text-align:center;"><div class="stat-bubble brown"><span class="stat-icon">⏱</span><span class="stat-value"><?= $r2['cnt'] ?></span><span class="stat-label">Shifts Today</span></div></div>
    <div style="text-align:center;"><div class="stat-bubble nude"><span class="stat-icon">🛢</span><span class="stat-value"><?= number_format($r4['vol'],0) ?>L</span><span class="stat-label">Sold Today</span></div></div>
    <div style="text-align:center;"><div class="stat-bubble success"><span class="stat-icon">💰</span><span class="stat-value">KES <?= number_format($r3['rev'],0) ?></span><span class="stat-label">Revenue Today</span></div></div>
</div>

<div class="grid-2">
    <div class="card">
        <div class="card-title">📦 Stock Levels</div>
        <?php while($row=$stock->fetch_assoc()): $low=$row['vol']<500; ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--nude-light);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                <span style="font-weight:600;color:var(--navy);">⛽ <?= clean($row['fuel_name']) ?></span>
                <span style="font-size:18px;font-weight:800;color:<?= $low?'var(--danger)':'var(--brown)' ?>;"><?= number_format($row['vol'],2) ?> L</span>
            </div>
            <div style="height:6px;background:var(--nude-light);border-radius:4px;overflow:hidden;">
                <div style="height:100%;width:<?= min(100,($row['vol']/10000)*100) ?>%;background:<?= $low?'var(--danger)':'linear-gradient(90deg,var(--navy),var(--brown))' ?>;border-radius:4px;"></div>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <div class="card">
        <div class="card-title">⚡ Quick Actions</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="/fms/admin/users.php"      class="btn btn-primary">👥 Manage Users</a>
            <a href="/fms/admin/fuel_types.php" class="btn btn-brown">⛽ Manage Fuel Types</a>
            <a href="/fms/admin/deliveries.php" class="btn btn-nude">🚛 View All Deliveries</a>
            <a href="/fms/admin/stock.php"      class="btn btn-nude">📦 Manage Stock</a>
            <a href="/fms/admin/reports.php"    class="btn btn-nude">📋 All Reports</a>
            <a href="/fms/admin/audit.php"      class="btn btn-nude">🔍 Audit Trail</a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">🧾 Recent Transactions</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Supervisor</th><th>Fuel</th><th>Opening</th><th>Closing</th><th>Volume (L)</th><th>Amount (KES)</th></tr></thead>
            <tbody>
                <?php if($recentTrans->num_rows===0): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px;">No transactions yet.</td></tr>
                <?php endif; ?>
                <?php while($row=$recentTrans->fetch_assoc()): ?>
                <tr>
                    <td><?= clean($row['trans_date']) ?></td>
                    <td>👷 <?= clean($row['name']) ?></td>
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