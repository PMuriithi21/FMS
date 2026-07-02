<?php
// admin/fuel_types.php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();
$pageTitle = 'Fuel Types';
$activeNav = 'fuel';
$success = $error = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add') {
    $name  = trim($_POST['fuel_name']??'');
    $price = trim($_POST['unit_price']??'');
    if (!$name||!$price) { $error='All fields required.'; }
    else {
        $stmt = $db->prepare("INSERT INTO fuel_types (fuel_name,unit_price) VALUES (?,?)");
        $stmt->bind_param('sd',$name,$price);
        if ($stmt->execute()) {
            // Add stock row
            $fid = $db->insert_id;
            $db->query("INSERT INTO stock (fuel_id,current_volume) VALUES ($fid,0.00)");
            $success = "Fuel type '$name' added.";
        } else { $error = 'Fuel type already exists.'; }
        $stmt->close();
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='update_price') {
    $fid   = (int)($_POST['fuel_id']??0);
    $price = trim($_POST['unit_price']??'');
    if ($fid && $price) {
        $stmt = $db->prepare("UPDATE fuel_types SET unit_price=? WHERE fuel_id=?");
        $stmt->bind_param('di',$price,$fid);
        $stmt->execute(); $stmt->close();
        $success = 'Price updated.';
    }
}

if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='toggle_fuel') {
    $fid = (int)($_POST['fuel_id']??0);
    $cur = $_POST['current_status']??'';
    $new = $cur==='active'?'inactive':'active';
    $db->prepare("UPDATE fuel_types SET status=? WHERE fuel_id=?")->bind_param('si',$new,$fid);
    // Simpler approach:
    $stmt = $db->prepare("UPDATE fuel_types SET status=? WHERE fuel_id=?");
    $stmt->bind_param('si',$new,$fid); $stmt->execute(); $stmt->close();
    $success = 'Fuel type status updated.';
}

$fuels = $db->query("SELECT f.*,COALESCE(s.current_volume,0) as stock_vol FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id ORDER BY f.fuel_name");
include __DIR__ . '/../includes/header.php';
?>
<?php if($success): ?><div class="alert alert-success"><?= clean($success) ?></div><?php endif; ?>
<?php if($error):   ?><div class="alert alert-error"><?= clean($error) ?></div><?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-title">➕ Add Fuel Type</div>
        <form method="POST">
            <input type="hidden" name="action" value="add"/>
            <div class="form-group"><label>Fuel Name *</label><input type="text" name="fuel_name" class="form-control" placeholder="e.g. Super Petrol" required/></div>
            <div class="form-group"><label>Unit Price (KES/L) *</label><input type="number" step="0.01" name="unit_price" class="form-control" placeholder="e.g. 219.00" required/></div>
            <button type="submit" class="btn btn-primary btn-block">➕ Add Fuel Type</button>
        </form>
    </div>

    <div class="card">
        <div class="card-title">💲 Update Fuel Price</div>
        <form method="POST">
            <input type="hidden" name="action" value="update_price"/>
            <div class="form-group">
                <label>Select Fuel</label>
                <select name="fuel_id" class="form-control" required>
                    <option value="">— Select —</option>
                    <?php
                    $fl = $db->query("SELECT fuel_id,fuel_name,unit_price FROM fuel_types ORDER BY fuel_name");
                    while($f=$fl->fetch_assoc()):
                    ?>
                    <option value="<?= $f['fuel_id'] ?>"><?= clean($f['fuel_name']) ?> (KES <?= number_format($f['unit_price'],2) ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group"><label>New Price (KES/L) *</label><input type="number" step="0.01" name="unit_price" class="form-control" placeholder="New price" required/></div>
            <button type="submit" class="btn btn-brown btn-block">💲 Update Price</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-title">⛽ All Fuel Types</div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Fuel Name</th><th>Unit Price (KES/L)</th><th>Current Stock (L)</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php while($row=$fuels->fetch_assoc()): ?>
                <tr>
                    <td style="font-weight:600;">⛽ <?= clean($row['fuel_name']) ?></td>
                    <td>KES <?= number_format($row['unit_price'],2) ?></td>
                    <td style="font-weight:700;color:<?= $row['stock_vol']<500?'var(--danger)':'var(--brown)' ?>;"><?= number_format($row['stock_vol'],2) ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="toggle_fuel"/>
                            <input type="hidden" name="fuel_id" value="<?= $row['fuel_id'] ?>"/>
                            <input type="hidden" name="current_status" value="<?= $row['status'] ?>"/>
                            <button class="btn btn-sm <?= $row['status']==='active'?'btn-danger':'btn-brown' ?>"><?= $row['status']==='active'?'Deactivate':'Activate' ?></button>
                        </form>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>