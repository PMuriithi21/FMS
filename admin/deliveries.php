<?php
// admin/deliveries.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';
requireRole('admin');
$db = getDB();

if (isset($_GET['read'])) {
    markNotificationAsRead((int)$_GET['read']);
}
$stmt = $db->prepare("
UPDATE notifications
SET is_read = 1
WHERE recipient_role='admin'
AND title='🚛 Delivery Received'
");

$stmt->execute();
$stmt->close();
$db = getDB();
$pageTitle = 'All Deliveries';
$activeNav = 'deliveries';

$month = $_GET['month'] ?? date('Y-m');
[$year,$mon] = explode('-',$month);

$stmt = $db->prepare("SELECT d.*, f.fuel_name, u.name as logged_by FROM deliveries d JOIN fuel_types f ON d.fuel_id=f.fuel_id JOIN users u ON d.user_id=u.user_id WHERE YEAR(d.delivery_date)=? AND MONTH(d.delivery_date)=? ORDER BY d.delivery_date DESC, d.created_at DESC");
$stmt->bind_param('ii',$year,$mon);
$stmt->execute();
$deliveries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalVol = array_sum(array_column($deliveries,'volume_received'));

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">🚛 All Deliveries</div>
    <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:24px;">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">Month:</label>
        <input type="month" name="month" value="<?= clean($month) ?>" class="form-control" style="width:180px;"/>
        <button type="submit" class="btn btn-brown btn-sm">Filter</button>
    </form>

    <div class="stats-grid" style="margin-bottom:24px;">
        <div style="text-align:center;">
            <div class="stat-bubble navy"><span class="stat-icon">🚛</span><span class="stat-value"><?= count($deliveries) ?></span><span class="stat-label">Total Deliveries</span></div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble brown"><span class="stat-icon">🛢</span><span class="stat-value"><?= number_format($totalVol,0) ?>L</span><span class="stat-label">Total Received</span></div>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Fuel</th><th>Volume (L)</th><th>Supplier</th><th>Logged By</th><th>Notes</th><th>Logged At</th></tr></thead>
            <tbody>
                <?php if(empty($deliveries)): ?>
                <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px;">No deliveries for <?= clean($month) ?>.</td></tr>
                <?php endif; ?>
                <?php foreach($deliveries as $row): ?>
                <tr>
                    <td><?= clean($row['delivery_date']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= number_format($row['volume_received'],2) ?></td>
                    <td><?= clean($row['supplier']) ?></td>
                    <td>👷 <?= clean($row['logged_by']) ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= clean($row['notes']??'—') ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= $row['created_at'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(!empty($deliveries)): ?>
                <tr style="background:var(--nude-pale);font-weight:800;">
                    <td colspan="2">TOTAL</td>
                    <td style="color:var(--navy);"><?= number_format($totalVol,2) ?> L</td>
                    <td colspan="4">—</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>