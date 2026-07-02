<?php
// admin/audit.php
require_once __DIR__ . '/../includes/config.php';
requireRole('admin');
$db = getDB();
$pageTitle = 'Audit Trail';
$activeNav = 'audit';

$date  = $_GET['date']  ?? date('Y-m-d');
$fuelF = $_GET['fuel']  ?? '';
$userF = $_GET['user']  ?? '';

// Build query dynamically
$where   = ["t.trans_date = ?"];
$params  = [$date];
$types   = 's';

if ($fuelF) { $where[] = "t.fuel_id = ?"; $params[] = (int)$fuelF; $types .= 'i'; }
if ($userF) { $where[] = "t.user_id = ?"; $params[] = (int)$userF; $types .= 'i'; }

$sql  = "SELECT t.*, f.fuel_name, u.name as sup_name, s.opening_meter as shift_open, s.closing_meter as shift_close FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id JOIN users u ON t.user_id=u.user_id JOIN shifts s ON t.shift_id=s.shift_id WHERE " . implode(' AND ',$where) . " ORDER BY t.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$fuels = $db->query("SELECT fuel_id, fuel_name FROM fuel_types WHERE status='active' ORDER BY fuel_name");
$supervisors = $db->query("SELECT user_id, name FROM users WHERE role='supervisor' AND status='active' ORDER BY name");

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">🔍 Transaction Audit Trail</div>

    <!-- Filters -->
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:24px;">
        <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:12px;">Date</label>
            <input type="date" name="date" value="<?= clean($date) ?>" class="form-control" style="width:160px;"/>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:12px;">Fuel Type</label>
            <select name="fuel" class="form-control" style="width:150px;">
                <option value="">All Fuels</option>
                <?php while($f=$fuels->fetch_assoc()): ?>
                <option value="<?= $f['fuel_id'] ?>" <?= $fuelF==$f['fuel_id']?'selected':'' ?>><?= clean($f['fuel_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label style="font-size:12px;">Supervisor</label>
            <select name="user" class="form-control" style="width:160px;">
                <option value="">All Supervisors</option>
                <?php while($u=$supervisors->fetch_assoc()): ?>
                <option value="<?= $u['user_id'] ?>" <?= $userF==$u['user_id']?'selected':'' ?>><?= clean($u['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-brown btn-sm">🔍 Filter</button>
        <a href="/fms/admin/audit.php" class="btn btn-nude btn-sm">Clear</a>
    </form>

    <p style="font-size:13px;color:var(--text-light);margin-bottom:16px;">
        Showing <strong><?= count($records) ?></strong> records for <strong><?= clean($date) ?></strong>
    </p>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Time</th>
                    <th>Supervisor</th>
                    <th>Fuel</th>
                    <th>Opening Meter</th>
                    <th>Closing Meter</th>
                    <th>Volume Sold (L)</th>
                    <th>Amount (KES)</th>
                    <th>Shift ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($records)): ?>
                <tr><td colspan="9" style="text-align:center;color:var(--text-light);padding:24px;">No records found for the selected filters.</td></tr>
                <?php endif; ?>
                <?php foreach($records as $i => $row): ?>
                <tr>
                    <td style="color:var(--text-light);font-size:12px;"><?= $row['transaction_id'] ?></td>
                    <td style="font-size:12px;"><?= substr($row['created_at'],11,8) ?></td>
                    <td style="font-weight:600;">👷 <?= clean($row['sup_name']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-family:monospace;"><?= number_format($row['opening_meter'],2) ?></td>
                    <td style="font-family:monospace;"><?= number_format($row['closing_meter'],2) ?></td>
                    <td style="font-weight:800;color:var(--brown);"><?= number_format($row['volume_sold'],2) ?></td>
                    <td style="font-weight:700;color:var(--navy);"><?= number_format($row['amount_paid'],2) ?></td>
                    <td style="font-size:12px;color:var(--text-light);">#<?= $row['shift_id'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if(!empty($records)): ?>
                <tr style="background:var(--nude-pale);font-weight:800;">
                    <td colspan="6">TOTAL</td>
                    <td style="color:var(--brown);"><?= number_format(array_sum(array_column($records,'volume_sold')),2) ?> L</td>
                    <td style="color:var(--navy);">KES <?= number_format(array_sum(array_column($records,'amount_paid')),2) ?></td>
                    <td>—</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>