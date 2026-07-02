<?php
// supervisor/dashboard.php
require_once __DIR__ . '/../includes/config.php';
requireRole('supervisor');
$db   = getDB();
$uid  = $_SESSION['user_id'];
$pageTitle = 'Supervisor Dashboard';
$activeNav = 'dashboard';

// Open shift for today
$today = date('Y-m-d');
$stmt  = $db->prepare("SELECT s.*, f.fuel_name FROM shifts s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE s.user_id=? AND s.shift_date=? AND s.status='open' LIMIT 1");
$stmt->bind_param('is', $uid, $today);
$stmt->execute();
$openShift = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Today's closed shifts
$stmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM shifts WHERE user_id=? AND shift_date=? AND status='closed'");
$stmt2->bind_param('is', $uid, $today);
$stmt2->execute();
$closedToday = $stmt2->get_result()->fetch_assoc()['cnt'];
$stmt2->close();

// Today's total volume sold
$stmt3 = $db->prepare("SELECT COALESCE(SUM(volume_sold),0) as total FROM transactions WHERE user_id=? AND trans_date=?");
$stmt3->bind_param('is', $uid, $today);
$stmt3->execute();
$todayVol = $stmt3->get_result()->fetch_assoc()['total'];
$stmt3->close();

// Today's total revenue
$stmt4 = $db->prepare("SELECT COALESCE(SUM(amount_paid),0) as total FROM transactions WHERE user_id=? AND trans_date=?");
$stmt4->bind_param('is', $uid, $today);
$stmt4->execute();
$todayRev = $stmt4->get_result()->fetch_assoc()['total'];
$stmt4->close();

// Stock levels
$stock = $db->query("SELECT f.fuel_name, s.current_volume FROM stock s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");

// Recent shifts (last 5)
$stmt5 = $db->prepare("SELECT s.*, f.fuel_name, (s.closing_meter - s.opening_meter) as vol_sold FROM shifts s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE s.user_id=? ORDER BY s.created_at DESC LIMIT 5");
$stmt5->bind_param('i', $uid);
$stmt5->execute();
$recentShifts = $stmt5->get_result();
$stmt5->close();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($openShift): ?>
<div class="alert alert-warning">
    ⏱ You have an <strong>open shift</strong> for <?= clean($openShift['fuel_name']) ?> started today.
    Opening meter: <strong><?= number_format($openShift['opening_meter'], 2) ?> L</strong>
    &nbsp;&nbsp;<a href="/fms/supervisor/shift.php" class="btn btn-brown btn-sm">Close Shift</a>
</div>
<?php endif; ?>

<!-- Stat bubbles -->
<div class="stats-grid">
    <div style="text-align:center;">
        <div class="stat-bubble navy">
            <span class="stat-icon">⏱</span>
            <span class="stat-value"><?= $openShift ? '1' : '0' ?></span>
            <span class="stat-label">Open Shift</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble brown">
            <span class="stat-icon">✅</span>
            <span class="stat-value"><?= $closedToday ?></span>
            <span class="stat-label">Closed Today</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble nude">
            <span class="stat-icon">🛢</span>
            <span class="stat-value"><?= number_format($todayVol, 0) ?>L</span>
            <span class="stat-label">Volume Sold</span>
        </div>
    </div>
    <div style="text-align:center;">
        <div class="stat-bubble success">
            <span class="stat-icon">💰</span>
            <span class="stat-value">KES <?= number_format($todayRev, 0) ?></span>
            <span class="stat-label">Revenue Today</span>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Stock levels -->
    <div class="card">
        <div class="card-title">📦 Current Stock Levels</div>
        <?php while($row = $stock->fetch_assoc()): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--nude-light);">
            <span style="font-weight:600;color:var(--navy);">⛽ <?= clean($row['fuel_name']) ?></span>
            <span style="font-size:18px;font-weight:800;color:var(--brown);"><?= number_format($row['current_volume'], 2) ?> L</span>
        </div>
        <?php endwhile; ?>
        <div style="margin-top:16px;">
            <a href="/fms/supervisor/shift.php" class="btn btn-primary btn-block">+ Start / Close Shift</a>
        </div>
    </div>

    <!-- Quick actions -->
    <div class="card">
        <div class="card-title">⚡ Quick Actions</div>
        <div style="display:flex;flex-direction:column;gap:12px;">
            <a href="/fms/supervisor/shift.php"    class="btn btn-primary">⏱ Manage Shift (Meter Entry)</a>
            <a href="/fms/supervisor/delivery.php" class="btn btn-brown">🚛 Log Fuel Delivery</a>
            <a href="/fms/supervisor/stock.php"    class="btn btn-nude">📦 View Stock Levels</a>
            <a href="/fms/supervisor/history.php"  class="btn btn-nude">📋 My Shift History</a>
        </div>
    </div>
</div>

<!-- Recent shifts -->
<div class="card">
    <div class="card-title">📋 Recent Shifts</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Fuel</th>
                    <th>Opening Meter</th>
                    <th>Closing Meter</th>
                    <th>Volume Sold (L)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $recentShifts->fetch_assoc()): ?>
                <tr>
                    <td><?= clean($row['shift_date']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['opening_meter'], 2) ?></td>
                    <td><?= $row['closing_meter'] ? number_format($row['closing_meter'], 2) : '—' ?></td>
                    <td><?= $row['closing_meter'] ? number_format($row['closing_meter'] - $row['opening_meter'], 2) : '—' ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>