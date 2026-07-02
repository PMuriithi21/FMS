<?php
// supervisor/history.php
require_once __DIR__ . '/../includes/config.php';
requireRole('supervisor');
$db  = getDB();
$uid = $_SESSION['user_id'];
$pageTitle = 'My Shift History';
$activeNav = 'history';

$month = $_GET['month'] ?? date('Y-m');
[$year, $mon] = explode('-', $month);

$stmt = $db->prepare("SELECT s.*, f.fuel_name, (s.closing_meter - s.opening_meter) as vol_sold FROM shifts s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE s.user_id=? AND YEAR(s.shift_date)=? AND MONTH(s.shift_date)=? ORDER BY s.shift_date DESC, s.created_at DESC");
$stmt->bind_param('iii', $uid, $year, $mon);
$stmt->execute();
$shifts = $stmt->get_result();
$stmt->close();

// Monthly totals
$stmt2 = $db->prepare("SELECT COALESCE(SUM(t.volume_sold),0) as total_vol, COALESCE(SUM(t.amount_paid),0) as total_rev FROM transactions t WHERE t.user_id=? AND YEAR(t.trans_date)=? AND MONTH(t.trans_date)=?");
$stmt2->bind_param('iii', $uid, $year, $mon);
$stmt2->execute();
$totals = $stmt2->get_result()->fetch_assoc();
$stmt2->close();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">📋 Shift History</div>
    <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:20px;">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">Month:</label>
        <input type="month" name="month" value="<?= clean($month) ?>" class="form-control" style="width:180px;"/>
        <button type="submit" class="btn btn-brown btn-sm">Filter</button>
    </form>

    <div class="stats-grid" style="margin-bottom:20px;">
        <div style="text-align:center;">
            <div class="stat-bubble navy">
                <span class="stat-icon">🛢</span>
                <span class="stat-value"><?= number_format($totals['total_vol'],0) ?></span>
                <span class="stat-label">Total Volume (L)</span>
            </div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble brown">
                <span class="stat-icon">💰</span>
                <span class="stat-value">KES <?= number_format($totals['total_rev'],0) ?></span>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>Fuel</th><th>Opening Meter</th><th>Closing Meter</th><th>Volume Sold (L)</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if ($shifts->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:20px;">No shifts found for this month.</td></tr>
                <?php endif; ?>
                <?php while($row = $shifts->fetch_assoc()): ?>
                <tr>
                    <td><?= clean($row['shift_date']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['opening_meter'],2) ?></td>
                    <td><?= $row['closing_meter'] ? number_format($row['closing_meter'],2) : '—' ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= $row['closing_meter'] ? number_format($row['vol_sold'],2) : '—' ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>