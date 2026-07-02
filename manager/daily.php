<?php
// manager/daily.php
require_once __DIR__ . '/../includes/config.php';
requireRole('manager');
$db = getDB();
$pageTitle = 'Daily Sales Report';
$activeNav = 'daily';

$date = $_GET['date'] ?? date('Y-m-d');

// Totals for selected date
$stmt = $db->prepare("SELECT f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev, COUNT(*) as shifts FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE t.trans_date=? GROUP BY t.fuel_id ORDER BY f.fuel_name");
$stmt->bind_param('s', $date);
$stmt->execute();
$dailyByFuel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Per supervisor
$stmt2 = $db->prepare("SELECT u.name, f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev FROM transactions t JOIN users u ON t.user_id=u.user_id JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE t.trans_date=? GROUP BY t.user_id, t.fuel_id ORDER BY u.name");
$stmt2->bind_param('s', $date);
$stmt2->execute();
$bySupervisor = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Grand totals
$totalVol = array_sum(array_column($dailyByFuel, 'vol'));
$totalRev = array_sum(array_column($dailyByFuel, 'rev'));

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">📅 Daily Sales Report</div>
    <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:24px;">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">Date:</label>
        <input type="date" name="date" value="<?= clean($date) ?>" class="form-control" style="width:180px;"/>
        <button type="submit" class="btn btn-brown btn-sm">View Report</button>
    </form>

    <!-- Summary bubbles -->
    <div class="stats-grid" style="margin-bottom:28px;">
        <div style="text-align:center;">
            <div class="stat-bubble navy">
                <span class="stat-icon">🛢</span>
                <span class="stat-value"><?= number_format($totalVol,0) ?>L</span>
                <span class="stat-label">Total Volume</span>
            </div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble brown">
                <span class="stat-icon">💰</span>
                <span class="stat-value">KES <?= number_format($totalRev,0) ?></span>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble nude">
                <span class="stat-icon">📋</span>
                <span class="stat-value"><?= count($bySupervisor) ?></span>
                <span class="stat-label">Shift Records</span>
            </div>
        </div>
    </div>

    <!-- By fuel type -->
    <h3 style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:12px;">Sales by Fuel Type</h3>
    <div class="table-wrap" style="margin-bottom:24px;">
        <table>
            <thead><tr><th>Fuel Type</th><th>Volume Sold (L)</th><th>Revenue (KES)</th><th>Shifts</th></tr></thead>
            <tbody>
                <?php if (empty($dailyByFuel)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:20px;">No transactions found for <?= clean($date) ?>.</td></tr>
                <?php endif; ?>
                <?php foreach($dailyByFuel as $row): ?>
                <tr>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-weight:700;"><?= number_format($row['vol'],2) ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= number_format($row['rev'],2) ?></td>
                    <td><?= $row['shifts'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!empty($dailyByFuel)): ?>
                <tr style="background:var(--nude-pale);font-weight:800;">
                    <td>TOTAL</td>
                    <td><?= number_format($totalVol,2) ?> L</td>
                    <td style="color:var(--navy);">KES <?= number_format($totalRev,2) ?></td>
                    <td>—</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- By supervisor -->
    <h3 style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:12px;">Sales by Supervisor</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Supervisor</th><th>Fuel</th><th>Volume (L)</th><th>Revenue (KES)</th></tr></thead>
            <tbody>
                <?php if (empty($bySupervisor)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:20px;">No data.</td></tr>
                <?php endif; ?>
                <?php foreach($bySupervisor as $row): ?>
                <tr>
                    <td>👷 <?= clean($row['name']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['vol'],2) ?></td>
                    <td style="color:var(--brown);font-weight:600;"><?= number_format($row['rev'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>