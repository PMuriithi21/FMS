<?php
// supervisor/delivery.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';
requireRole('supervisor');
$db  = getDB();
$uid = $_SESSION['user_id'];
$pageTitle = 'Log Fuel Delivery';
$activeNav = 'delivery';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fuel_id         = (int)($_POST['fuel_id'] ?? 0);
    $volume_received = trim($_POST['volume_received'] ?? '');
    $supplier        = trim($_POST['supplier'] ?? '');
    $delivery_date   = trim($_POST['delivery_date'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if (!$fuel_id || $volume_received === '' || $supplier === '' || $delivery_date === '') {
        $error = 'Please fill in all required fields.';
    } elseif ($volume_received <= 0) {
        $error = 'Volume received must be greater than zero.';
    } else {
        $db->begin_transaction();
        try {
            // Insert delivery
            $ins = $db->prepare("INSERT INTO deliveries (user_id, fuel_id, volume_received, supplier, delivery_date, notes) VALUES (?,?,?,?,?,?)");
            $ins->bind_param('iidsss', $uid, $fuel_id, $volume_received, $supplier, $delivery_date, $notes);
            $ins->execute();
            $ins->close();

            // Increment stock
            $stk = $db->prepare("UPDATE stock SET current_volume = current_volume + ? WHERE fuel_id=?");
            $stk->bind_param('di', $volume_received, $fuel_id);
            $stk->execute();
            $stk->close();

            // Get updated stock level
$stmt = $db->prepare("
SELECT
    f.fuel_name,
    s.current_volume
FROM stock s
JOIN fuel_types f
ON s.fuel_id=f.fuel_id
WHERE s.fuel_id=?
");

$stmt->bind_param("i",$fuel_id);
$stmt->execute();

$stock = $stmt->get_result()->fetch_assoc();

$stmt->close();


// Notify Admin
createNotification(
    "🚛 Delivery Received",
    $volume_received." L of ".$stock['fuel_name'].
    " received from ".$supplier.
    ". Current stock: ".
    number_format($stock['current_volume'],2)." L.",
    "admin",
    "delivery",
    "success"
);

// Notify Manager
createNotification(
    "🚛 Delivery Received",
    $volume_received." L of ".$stock['fuel_name'].
    " received from ".$supplier.
    ". Current stock: ".
    number_format($stock['current_volume'],2)." L.",
    "manager",
    "delivery",
    "success"
);

            $db->commit();
            $success = '✅ Delivery logged successfully. Stock updated by ' . number_format($volume_received, 2) . ' L.';
        } catch (Exception $e) {
            $db->rollback();
            $error = 'Error saving delivery: ' . $e->getMessage();
        }
    }
}

// Load fuel types
$fuels = $db->query("SELECT f.fuel_id, f.fuel_name, COALESCE(s.current_volume,0) as stock_vol FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
$fuelList = $fuels->fetch_all(MYSQLI_ASSOC);

// Recent deliveries
$stmt = $db->prepare("SELECT d.*, f.fuel_name FROM deliveries d JOIN fuel_types f ON d.fuel_id=f.fuel_id WHERE d.user_id=? ORDER BY d.created_at DESC LIMIT 10");
$stmt->bind_param('i', $uid);
$stmt->execute();
$recentDeliveries = $stmt->get_result();
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= clean($error) ?></div><?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-title">🚛 Record Fuel Delivery</div>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
            Record fuel received from supplier. Stock will be updated automatically.
        </p>
        <form method="POST">
            <div class="form-group">
                <label>Fuel Type *</label>
                <div class="fuel-selector">
                    <?php foreach($fuelList as $f): ?>
                    <div class="fuel-btn" onclick="selectFuel(<?= $f['fuel_id'] ?>, this)">
                        <div class="fuel-name">⛽ <?= clean($f['fuel_name']) ?></div>
                        <div class="fuel-stock"><?= number_format($f['stock_vol'],0) ?> L in tank</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="fuel_id" id="selectedFuel" value=""/>
            </div>
            <div class="form-group">
                <label>Volume Received (Litres) *</label>
                <input type="number" step="0.01" min="0.01" name="volume_received" class="form-control" placeholder="e.g. 5000.00" required/>
            </div>
            <div class="form-group">
                <label>Supplier Name *</label>
                <input type="text" name="supplier" class="form-control" placeholder="e.g. Total Kenya Ltd" required/>
            </div>
            <div class="form-group">
                <label>Delivery Date *</label>
                <input type="date" name="delivery_date" class="form-control" value="<?= date('Y-m-d') ?>" required/>
            </div>
            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="e.g. Delivery note #1234"></textarea>
            </div>
            <button type="submit" class="btn btn-brown btn-block">🚛 Log Delivery</button>
        </form>
    </div>

    <!-- Stock after delivery preview -->
    <div class="card">
        <div class="card-title">📦 Current Stock Levels</div>
        <?php foreach($fuelList as $f): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;border-bottom:1px solid var(--nude-light);">
            <span style="font-weight:600;color:var(--navy);">⛽ <?= clean($f['fuel_name']) ?></span>
            <div style="text-align:right;">
                <div style="font-size:20px;font-weight:800;color:var(--brown);"><?= number_format($f['stock_vol'],2) ?> L</div>
                <div style="font-size:11px;color:var(--text-light);">in tank</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent deliveries table -->
<div class="card">
    <div class="card-title">📋 Recent Deliveries</div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Date</th><th>Fuel</th><th>Volume (L)</th><th>Supplier</th><th>Notes</th><th>Logged At</th></tr>
            </thead>
            <tbody>
                <?php if ($recentDeliveries->num_rows === 0): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:20px;">No deliveries recorded yet.</td></tr>
                <?php endif; ?>
                <?php while($row = $recentDeliveries->fetch_assoc()): ?>
                <tr>
                    <td><?= clean($row['delivery_date']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= number_format($row['volume_received'],2) ?></td>
                    <td><?= clean($row['supplier']) ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= clean($row['notes'] ?? '—') ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= $row['created_at'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function selectFuel(id, el) {
    document.querySelectorAll('.fuel-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedFuel').value = id;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>