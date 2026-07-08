<?php
// manager/dashboard.php
require_once __DIR__ . '/../includes/config.php';
requireRole('manager');
$db    = getDB();
$today = date('Y-m-d');
$month = date('Y-m');
[$year,$mon] = explode('-',$month);
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

// =====================================================
// CHART DATA: Revenue trend (last 7 days)
// =====================================================
$trendStmt = $db->prepare("
    SELECT trans_date, COALESCE(SUM(amount_paid),0) as rev, COALESCE(SUM(volume_sold),0) as vol
    FROM transactions
    WHERE trans_date >= DATE_SUB(?, INTERVAL 6 DAY)
    GROUP BY trans_date
    ORDER BY trans_date ASC
");
$trendStmt->bind_param('s', $today);
$trendStmt->execute();
$trendRaw = $trendStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$trendStmt->close();

$trendMap = [];
foreach ($trendRaw as $row) {
    $trendMap[$row['trans_date']] = $row;
}
$trendLabels = [];
$trendRevenue = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days", strtotime($today)));
    $trendLabels[] = date('D d M', strtotime($d));
    $trendRevenue[] = isset($trendMap[$d]) ? (float)$trendMap[$d]['rev'] : 0;
}

// =====================================================
// CHART DATA: Fuel distribution (this month)
// =====================================================
$fuelDistStmt = $db->prepare("
    SELECT f.fuel_name, COALESCE(SUM(t.volume_sold),0) as vol
    FROM transactions t
    JOIN fuel_types f ON t.fuel_id = f.fuel_id
    WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=?
    GROUP BY t.fuel_id
    ORDER BY f.fuel_name
");
$fuelDistStmt->bind_param('ii', $year, $mon);
$fuelDistStmt->execute();
$fuelDist = $fuelDistStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$fuelDistStmt->close();

$fuelLabels = array_column($fuelDist, 'fuel_name');
$fuelVolumes = array_map('floatval', array_column($fuelDist, 'vol'));

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
    <div class="card">
        <div class="card-title">📈 Revenue Trend (Last 7 Days)</div>
        <canvas id="revenueTrendChart" style="max-height:280px;"></canvas>
    </div>
    <div class="card">
        <div class="card-title">🥧 Fuel Sales Distribution (This Month)</div>
        <canvas id="fuelDistChart" style="max-height:280px;"></canvas>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
<script>
const trendLabels = <?= json_encode($trendLabels) ?>;
const trendRevenue = <?= json_encode($trendRevenue) ?>;

new Chart(document.getElementById('revenueTrendChart'), {
    type: 'line',
    data: {
        labels: trendLabels,
        datasets: [{
            label: 'Revenue (KES)',
            data: trendRevenue,
            borderColor: '#8b5e3c',
            backgroundColor: 'rgba(139,94,60,0.1)',
            tension: 0.3,
            fill: true,
            pointBackgroundColor: '#1a2744',
            pointRadius: 4
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { callback: v => 'KES ' + v.toLocaleString() } }
        }
    }
});

const fuelLabels = <?= json_encode($fuelLabels) ?>;
const fuelVolumes = <?= json_encode($fuelVolumes) ?>;

new Chart(document.getElementById('fuelDistChart'), {
    type: 'pie',
    data: {
        labels: fuelLabels,
        datasets: [{
            data: fuelVolumes,
            backgroundColor: ['#1a2744', '#8b5e3c', '#d9c7a8', '#4a7c59', '#a63d40']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>