<?php
// admin/stock.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('admin');
$db = getDB();

if (isset($_GET['read'])) {
    markNotificationAsRead((int)$_GET['read']);
}
$pageTitle = 'Stock Management';
$activeNav = 'stock';
$success = $error = '';

// Manual stock adjustment
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='adjust') {
    $fuel_id = (int)($_POST['fuel_id']??0);
    $new_vol = trim($_POST['new_volume']??'');
    $reason  = trim($_POST['reason']??'');
    if (!$fuel_id || $new_vol==='' || !$reason) {
        $error = 'All fields required.';
    } elseif ($new_vol < 0) {
        $error = 'Volume cannot be negative.';
    } else {
        $stmt = $db->prepare("UPDATE stock SET current_volume=? WHERE fuel_id=?");
        $stmt->bind_param('di',$new_vol,$fuel_id);
        $stmt->execute(); $stmt->close();
        $success = "Stock adjusted successfully. Reason: $reason";
    }
}

$stock = $db->query("SELECT f.fuel_id, f.fuel_name, f.unit_price, COALESCE(s.current_volume,0) as vol, s.last_updated FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
$stockData = $stock->fetch_all(MYSQLI_ASSOC);

include __DIR__ . '/../includes/header.php';
?>
<?php if($success): ?><div class="alert alert-success"><?= clean($success) ?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-error"><?= clean($error) ?></div><?php endif; ?>

<div class="stats-grid" style="margin-bottom:24px;">
    <?php foreach($stockData as $row): $low=$row['vol']<500; ?>
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

<div class="grid-2">
    <div class="card">
        <div class="card-title">⚙️ Manual Stock Adjustment</div>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:16px;">Use only for corrections after physical stock verification.</p>
        <form method="POST">
            <input type="hidden" name="action" value="adjust"/>
            <div class="form-group">
                <label>Fuel Type *</label>
                <select name="fuel_id" class="form-control" required>
                    <option value="">— Select fuel —</option>
                    <?php foreach($stockData as $f): ?>
                    <option value="<?= $f['fuel_id'] ?>"><?= clean($f['fuel_name']) ?> — Current: <?= number_format($f['vol'],2) ?> L</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>New Volume (L) *</label><input type="number" step="0.01" min="0" name="new_volume" class="form-control" placeholder="e.g. 4500.00" required/></div>
            <div class="form-group"><label>Reason for Adjustment *</label><input type="text" name="reason" class="form-control" placeholder="e.g. Physical dip count correction" required/></div>
            <button type="submit" class="btn btn-primary btn-block">⚙️ Apply Adjustment</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">📦 Stock Detail</div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Fuel</th><th>Volume (L)</th><th>Value (KES)</th><th>Last Updated</th></tr></thead>
                <tbody>
                    <?php foreach($stockData as $row): ?>
                    <tr>
                        <td style="font-weight:600;">⛽ <?= clean($row['fuel_name']) ?></td>
                        <td style="font-weight:700;color:<?= $row['vol']<500?'var(--danger)':'var(--brown)' ?>;"><?= number_format($row['vol'],2) ?></td>
                        <td style="color:var(--navy);">KES <?= number_format($row['vol']*$row['unit_price'],2) ?></td>
                        <td style="font-size:12px;color:var(--text-light);"><?= $row['last_updated']??'—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>