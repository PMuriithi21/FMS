<?php
// admin/reports_pdf.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireRole('admin');
$db = getDB();

$month = $_GET['month'] ?? date('Y-m');
[$year, $mon] = explode('-', $month);

// Monthly totals by fuel
$stmt = $db->prepare("SELECT f.fuel_name, COALESCE(SUM(t.volume_sold),0) as vol, COALESCE(SUM(t.amount_paid),0) as rev, COUNT(*) as shifts FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.fuel_id ORDER BY f.fuel_name");
$stmt->bind_param('ii',$year,$mon);
$stmt->execute();
$byFuel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Monthly totals by supervisor
$stmt2 = $db->prepare("SELECT u.name, COALESCE(SUM(t.volume_sold),0) as vol, COALESCE(SUM(t.amount_paid),0) as rev, COUNT(*) as shifts FROM transactions t JOIN users u ON t.user_id=u.user_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.user_id ORDER BY u.name");
$stmt2->bind_param('ii',$year,$mon);
$stmt2->execute();
$bySup = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

// Daily breakdown
$stmt3 = $db->prepare("SELECT t.trans_date, COALESCE(SUM(t.volume_sold),0) as vol, COALESCE(SUM(t.amount_paid),0) as rev FROM transactions t WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.trans_date ORDER BY t.trans_date DESC");
$stmt3->bind_param('ii',$year,$mon);
$stmt3->execute();
$daily = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt3->close();

// Deliveries this month
$stmt4 = $db->prepare("SELECT f.fuel_name, COALESCE(SUM(d.volume_received),0) as vol, COUNT(*) as cnt FROM deliveries d JOIN fuel_types f ON d.fuel_id=f.fuel_id WHERE YEAR(d.delivery_date)=? AND MONTH(d.delivery_date)=? GROUP BY d.fuel_id ORDER BY f.fuel_name");
$stmt4->bind_param('ii',$year,$mon);
$stmt4->execute();
$delivSummary = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt4->close();

$totalVol = array_sum(array_column($byFuel,'vol'));
$totalRev = array_sum(array_column($byFuel,'rev'));
$totalDeliv = array_sum(array_column($delivSummary,'vol'));
$monthLabel = date('F Y', strtotime($month . '-01'));

// =====================================
// BUILD HTML FOR PDF
// =====================================

ob_start();
?>
<html>
<head>
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 18px; color: #1a2744; margin-bottom: 4px; }
    .subtitle { font-size: 12px; color: #666; margin-bottom: 20px; }
    .stats { width: 100%; margin-bottom: 20px; }
    .stats td { width: 25%; text-align: center; padding: 10px; border: 1px solid #ddd; }
    .stat-label { font-size: 10px; color: #666; text-transform: uppercase; }
    .stat-value { font-size: 15px; font-weight: bold; color: #1a2744; }
    table.data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.data th { background: #1a2744; color: #fff; padding: 6px 8px; text-align: left; font-size: 11px; }
    table.data td { padding: 6px 8px; border-bottom: 1px solid #eee; font-size: 11px; }
    table.data tr.total-row { background: #f5f0e8; font-weight: bold; }
    h2 { font-size: 14px; color: #1a2744; margin-top: 25px; margin-bottom: 8px; }
    .footer { margin-top: 30px; font-size: 10px; color: #999; text-align: center; }
</style>
</head>
<body>

    <h1>⛽ Fuel Management System</h1>
    <div class="subtitle">Comprehensive Monthly Report — <?= clean($monthLabel) ?></div>

    <table class="stats">
        <tr>
            <td><div class="stat-value"><?= number_format($totalVol,0) ?> L</div><div class="stat-label">Total Sold</div></td>
            <td><div class="stat-value">KES <?= number_format($totalRev,0) ?></div><div class="stat-label">Total Revenue</div></td>
            <td><div class="stat-value"><?= number_format($totalDeliv,0) ?> L</div><div class="stat-label">Total Received</div></td>
            <td><div class="stat-value"><?= count($daily) ?></div><div class="stat-label">Active Days</div></td>
        </tr>
    </table>

    <h2>Sales by Fuel Type</h2>
    <table class="data">
        <thead><tr><th>Fuel Type</th><th>Volume Sold (L)</th><th>Revenue (KES)</th><th>Shifts</th></tr></thead>
        <tbody>
            <?php if (empty($byFuel)): ?>
            <tr><td colspan="4" style="text-align:center;color:#999;">No data for <?= clean($monthLabel) ?>.</td></tr>
            <?php endif; ?>
            <?php foreach($byFuel as $row): ?>
            <tr>
                <td>⛽ <?= clean($row['fuel_name']) ?></td>
                <td><?= number_format($row['vol'],2) ?></td>
                <td><?= number_format($row['rev'],2) ?></td>
                <td><?= $row['shifts'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!empty($byFuel)): ?>
            <tr class="total-row">
                <td>TOTAL</td>
                <td><?= number_format($totalVol,2) ?> L</td>
                <td>KES <?= number_format($totalRev,2) ?></td>
                <td>—</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h2>Sales by Supervisor</h2>
    <table class="data">
        <thead><tr><th>Supervisor</th><th>Volume Sold (L)</th><th>Revenue (KES)</th><th>Shifts</th></tr></thead>
        <tbody>
            <?php if (empty($bySup)): ?>
            <tr><td colspan="4" style="text-align:center;color:#999;">No data.</td></tr>
            <?php endif; ?>
            <?php foreach($bySup as $row): ?>
            <tr>
                <td>👷 <?= clean($row['name']) ?></td>
                <td><?= number_format($row['vol'],2) ?></td>
                <td><?= number_format($row['rev'],2) ?></td>
                <td><?= $row['shifts'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Deliveries Received</h2>
    <table class="data">
        <thead><tr><th>Fuel Type</th><th>Total Volume Received (L)</th><th>No. of Deliveries</th></tr></thead>
        <tbody>
            <?php if (empty($delivSummary)): ?>
            <tr><td colspan="3" style="text-align:center;color:#999;">No deliveries this month.</td></tr>
            <?php endif; ?>
            <?php foreach($delivSummary as $row): ?>
            <tr>
                <td>⛽ <?= clean($row['fuel_name']) ?></td>
                <td><?= number_format($row['vol'],2) ?></td>
                <td><?= $row['cnt'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Daily Sales Breakdown</h2>
    <table class="data">
        <thead><tr><th>Date</th><th>Volume Sold (L)</th><th>Revenue (KES)</th></tr></thead>
        <tbody>
            <?php if (empty($daily)): ?>
            <tr><td colspan="3" style="text-align:center;color:#999;">No transactions recorded.</td></tr>
            <?php endif; ?>
            <?php foreach($daily as $row): ?>
            <tr>
                <td><?= clean($row['trans_date']) ?></td>
                <td><?= number_format($row['vol'],2) ?></td>
                <td><?= number_format($row['rev'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        Generated on <?= date('d M Y, H:i') ?> — Fuel Management System
    </div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'Comprehensive_Report_' . $month . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit();