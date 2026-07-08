<?php
// manager/stock.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';
requireRole('manager');
$db = getDB();

if (isset($_GET['read'])) {
    markNotificationAsRead((int)$_GET['read']);
}
$pageTitle = 'Stock Status';
$activeNav = 'stock';

$stock = $db->query("SELECT f.fuel_name, f.unit_price, COALESCE(s.current_volume,0) as vol, s.last_updated FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
$stockData = $stock->fetch_all(MYSQLI_ASSOC);

// This month deliveries vs sales per fuel
$month = date('Y-m');
[$year,$mon] = explode('-', $month);

$stmt = $db->prepare("SELECT f.fuel_name, COALESCE(SUM(d.volume_received),0) as received, COALESCE(SUM(t.volume_sold),0) as sold FROM fuel_types f LEFT JOIN deliveries d ON f.fuel_id=d.fuel_id AND YEAR(d.delivery_date)=? AND MONTH(d.delivery_date)=? LEFT JOIN transactions t ON f.fuel_id=t.fuel_id AND YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? WHERE f.status='active' GROUP BY f.fuel_id ORDER BY f.fuel_name");
$stmt->bind_param('iiii', $year, $mon, $year, $mon);
$stmt->execute();
$monthFlow = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>
<div class="stats-grid" style="margin-bottom:24px;">
    <?php foreach($stockData as $row): $low = $row['vol'] < 500; ?>
    <div style="text-align:center;">
        <div class="stat-bubble <?= $low?'':'navy' ?>" style="<?= $low?'border-color:var(--danger)':'' ?>">
            <span class="stat-icon">⛽</span>
            <span class="stat-value" style="<?= $low?'color:var(--danger)':'' ?>"><?= number_format($row['vol'],0) ?>L</span>
            <span class="stat-label"><?= clean($row['fuel_name']) ?></span>
        </div>
        <?php if($low): ?><p style="color:var(--danger);font-size:11px;margin-top:6px;font-weight:600;">⚠️ LOW STOCK</p><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-title">📦 Stock Detail</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fuel</th><th>Current Volume (L)</th><th>Unit Price (KES)</th><th>Est. Value (KES)</th><th>Last Updated</th></tr></thead>
            <tbody>
                <?php foreach($stockData as $row): $low = $row['vol'] < 500; ?>
                <tr <?= $low?'style="background:#fff5f5;"':'' ?>>
                    <td style="font-weight:600;">⛽ <?= clean($row['fuel_name']) ?> <?= $low?'<span class="badge" style="background:#fde8e8;color:var(--danger);">LOW</span>':'' ?></td>
                    <td style="font-weight:700;"><?= number_format($row['vol'],2) ?></td>
                    <td><?= number_format($row['unit_price'],2) ?></td>
                    <td style="color:var(--brown);font-weight:600;"><?= number_format($row['vol']*$row['unit_price'],2) ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= $row['last_updated']??'—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-title">📊 This Month — Stock Flow</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fuel</th><th>Received (L)</th><th>Sold (L)</th><th>Net Change (L)</th></tr></thead>
            <tbody>
                <?php foreach($monthFlow as $row): $net = $row['received'] - $row['sold']; ?>
                <tr>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="color:var(--success);font-weight:600;">+<?= number_format($row['received'],2) ?></td>
                    <td style="color:var(--danger);font-weight:600;">-<?= number_format($row['sold'],2) ?></td>
                    <td style="font-weight:700;color:<?= $net>=0?'var(--success)':'var(--danger)' ?>;"><?= ($net>=0?'+':'').number_format($net,2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>