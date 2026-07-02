<?php
// supervisor/shift.php  — meter-based shift entry
require_once __DIR__ . '/../includes/config.php';
requireRole('supervisor');
$db    = getDB();
$uid   = $_SESSION['user_id'];
$today = date('Y-m-d');
$pageTitle = 'Shift & Meter Entry';
$activeNav = 'shift';

$success = $error = '';

// ── Handle START SHIFT ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_shift') {
    $fuel_id       = (int)($_POST['fuel_id'] ?? 0);
    $opening_meter = trim($_POST['opening_meter'] ?? '');

    if (!$fuel_id || $opening_meter === '') {
        $error = 'Please select a fuel type and enter the opening meter reading.';
    } else {
        // Check no open shift for this fuel today
        $chk = $db->prepare("SELECT shift_id FROM shifts WHERE user_id=? AND fuel_id=? AND shift_date=? AND status='open' LIMIT 1");
        $chk->bind_param('iis', $uid, $fuel_id, $today);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'You already have an open shift for this fuel type today. Close it first.';
        } else {
            $stmt = $db->prepare("INSERT INTO shifts (user_id, fuel_id, shift_date, opening_meter, status) VALUES (?,?,?,?,'open')");
            $stmt->bind_param('iisd', $uid, $fuel_id, $today, $opening_meter);
            $stmt->execute();
            $stmt->close();
            $success = '✅ Shift started. Opening meter recorded: ' . number_format($opening_meter, 2) . ' L';
        }
        $chk->close();
    }
}

// ── Handle CLOSE SHIFT ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close_shift') {
    $shift_id      = (int)($_POST['shift_id'] ?? 0);
    $closing_meter = trim($_POST['closing_meter'] ?? '');
    $amount_paid   = trim($_POST['amount_paid'] ?? '');

    if (!$shift_id || $closing_meter === '' || $amount_paid === '') {
        $error = 'Please fill in all fields to close the shift.';
    } else {
        // Get opening meter to validate
        $s = $db->prepare("SELECT opening_meter, fuel_id FROM shifts WHERE shift_id=? AND user_id=? AND status='open' LIMIT 1");
        $s->bind_param('ii', $shift_id, $uid);
        $s->execute();
        $shiftRow = $s->get_result()->fetch_assoc();
        $s->close();

        if (!$shiftRow) {
            $error = 'Shift not found or already closed.';
        } elseif ($closing_meter < $shiftRow['opening_meter']) {
            $error = 'Closing meter cannot be less than opening meter (' . number_format($shiftRow['opening_meter'], 2) . ').';
        } else {
            $volume_sold = $closing_meter - $shiftRow['opening_meter'];

            // Begin transaction
            $db->begin_transaction();
            try {
                // Close shift
                $upd = $db->prepare("UPDATE shifts SET closing_meter=?, status='closed', closed_at=NOW() WHERE shift_id=?");
                $upd->bind_param('di', $closing_meter, $shift_id);
                $upd->execute();
                $upd->close();

                // Insert transaction record
                $ins = $db->prepare("INSERT INTO transactions (shift_id, user_id, fuel_id, opening_meter, closing_meter, amount_paid, trans_date) VALUES (?,?,?,?,?,?,?)");
                $ins->bind_param('iiiddds', $shift_id, $uid, $shiftRow['fuel_id'], $shiftRow['opening_meter'], $closing_meter, $amount_paid, $today);
                $ins->execute();
                $ins->close();

                // Deduct from stock
                $stk = $db->prepare("UPDATE stock SET current_volume = current_volume - ? WHERE fuel_id=? AND current_volume >= ?");
                $stk->bind_param('did', $volume_sold, $shiftRow['fuel_id'], $volume_sold);
                $stk->execute();
                if ($db->affected_rows === 0) {
                    throw new Exception('Insufficient stock to deduct ' . number_format($volume_sold, 2) . ' L.');
                }
                $stk->close();

                $db->commit();
                $success = '✅ Shift closed. Volume sold: ' . number_format($volume_sold, 2) . ' L | Revenue: KES ' . number_format($amount_paid, 2);
            } catch (Exception $e) {
                $db->rollback();
                $error = '⚠️ ' . $e->getMessage();
            }
        }
    }
}

// ── Load data ─────────────────────────────────────────────────
// Fuel types
$fuels = $db->query("SELECT f.fuel_id, f.fuel_name, f.unit_price, COALESCE(s.current_volume,0) as stock_vol FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
$fuelList = $fuels->fetch_all(MYSQLI_ASSOC);

// Open shifts today
$os = $db->prepare("SELECT s.*, f.fuel_name FROM shifts s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE s.user_id=? AND s.shift_date=? AND s.status='open'");
$os->bind_param('is', $uid, $today);
$os->execute();
$openShifts = $os->get_result()->fetch_all(MYSQLI_ASSOC);
$os->close();

include __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>
<?php if ($error):   ?><div class="alert alert-error"><?= clean($error) ?></div><?php endif; ?>

<div class="grid-2">

    <!-- ── START SHIFT ── -->
    <div class="card">
        <div class="card-title">⏱ Start Shift — Opening Meter</div>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
            Select the fuel type and enter the pump meter reading at the <strong>start of your shift</strong>.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="start_shift"/>

            <div class="form-group">
                <label>Select Fuel Type</label>
                <div class="fuel-selector" id="fuelSelector">
                    <?php foreach($fuelList as $f): ?>
                    <div class="fuel-btn" onclick="selectFuel(<?= $f['fuel_id'] ?>, this)">
                        <div class="fuel-name">⛽ <?= clean($f['fuel_name']) ?></div>
                        <div class="fuel-stock"><?= number_format($f['stock_vol'], 0) ?> L in tank</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="fuel_id" id="selectedFuel" value=""/>
            </div>

            <div class="meter-display">
                <div>
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;opacity:0.7;">Opening Meter Reading</div>
                    <div class="meter-val" id="meterPreview">0.00</div>
                </div>
                <div style="opacity:0.5;font-size:28px;">L</div>
            </div>

            <div class="form-group">
                <label for="opening_meter">Opening Meter Reading (Litres)</label>
                <input type="number" step="0.01" min="0" id="opening_meter" name="opening_meter"
                       class="form-control" placeholder="e.g. 10450.00"
                       oninput="document.getElementById('meterPreview').textContent=parseFloat(this.value||0).toFixed(2)"/>
            </div>

            <button type="submit" class="btn btn-primary btn-block">▶ Start Shift</button>
        </form>
    </div>

    <!-- ── CLOSE SHIFT ── -->
    <div class="card">
        <div class="card-title">🏁 Close Shift — Closing Meter</div>
        <?php if (empty($openShifts)): ?>
        <div class="alert alert-warning">No open shifts to close today. Start a shift first.</div>
        <?php else: ?>
        <p style="font-size:13px;color:var(--text-light);margin-bottom:20px;">
            Select an open shift and enter the pump meter reading at the <strong>end of your shift</strong>.
            The system will automatically calculate volume sold.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="close_shift"/>

            <div class="form-group">
                <label>Select Open Shift</label>
                <select name="shift_id" class="form-control" onchange="updateOpeningDisplay(this)" required>
                    <option value="">— Select shift to close —</option>
                    <?php foreach($openShifts as $os): ?>
                    <option value="<?= $os['shift_id'] ?>" data-opening="<?= $os['opening_meter'] ?>">
                        <?= clean($os['fuel_name']) ?> — Opening: <?= number_format($os['opening_meter'], 2) ?> L
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="meter-display" id="closeDisplay" style="display:none;">
                <div>
                    <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:1px;">Opening Meter</div>
                    <div class="meter-val" id="openingVal">—</div>
                </div>
                <div style="font-size:24px;opacity:0.4;">→</div>
                <div>
                    <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:1px;">Closing Meter</div>
                    <div class="meter-val" id="closingVal">0.00</div>
                </div>
                <div>
                    <div style="font-size:11px;opacity:0.7;text-transform:uppercase;letter-spacing:1px;">Volume Sold</div>
                    <div class="meter-val" id="volSold" style="color:var(--nude-dark);">0.00</div>
                </div>
            </div>

            <div class="form-group">
                <label for="closing_meter">Closing Meter Reading (Litres)</label>
                <input type="number" step="0.01" min="0" id="closing_meter" name="closing_meter"
                       class="form-control" placeholder="e.g. 10820.00"
                       oninput="calcVolume()"/>
            </div>

            <div class="form-group">
                <label for="amount_paid">Total Cash Collected (KES)</label>
                <input type="number" step="0.01" min="0" id="amount_paid" name="amount_paid"
                       class="form-control" placeholder="e.g. 81130.00"/>
            </div>

            <button type="submit" class="btn btn-brown btn-block">🏁 Close Shift & Save</button>
        </form>
        <?php endif; ?>
    </div>

</div>

<!-- Today's shifts -->
<div class="card">
    <div class="card-title">📋 Today's Shifts</div>
    <?php
    $ts = $db->prepare("SELECT s.*, f.fuel_name FROM shifts s JOIN fuel_types f ON s.fuel_id=f.fuel_id WHERE s.user_id=? AND s.shift_date=? ORDER BY s.created_at DESC");
    $ts->bind_param('is', $uid, $today);
    $ts->execute();
    $todayShifts = $ts->get_result();
    $ts->close();
    if ($todayShifts->num_rows === 0):
    ?>
    <p style="color:var(--text-light);text-align:center;padding:20px;">No shifts recorded today yet.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Fuel</th><th>Opening Meter</th><th>Closing Meter</th><th>Volume Sold (L)</th><th>Status</th><th>Opened At</th></tr>
            </thead>
            <tbody>
                <?php while($row = $todayShifts->fetch_assoc()): ?>
                <tr>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['opening_meter'], 2) ?></td>
                    <td><?= $row['closing_meter'] ? number_format($row['closing_meter'], 2) : '—' ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= $row['closing_meter'] ? number_format($row['closing_meter'] - $row['opening_meter'], 2) : '—' ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= $row['created_at'] ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function selectFuel(id, el) {
    document.querySelectorAll('.fuel-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('selectedFuel').value = id;
}
function updateOpeningDisplay(sel) {
    const opt = sel.options[sel.selectedIndex];
    const opening = opt.dataset.opening;
    if (opening) {
        document.getElementById('closeDisplay').style.display = 'flex';
        document.getElementById('openingVal').textContent = parseFloat(opening).toFixed(2);
        calcVolume();
    }
}
function calcVolume() {
    const sel = document.querySelector('[name="shift_id"]');
    const opt = sel ? sel.options[sel.selectedIndex] : null;
    const opening = opt ? parseFloat(opt.dataset.opening || 0) : 0;
    const closing = parseFloat(document.getElementById('closing_meter').value || 0);
    const vol = Math.max(0, closing - opening);
    document.getElementById('closingVal').textContent = closing.toFixed(2);
    document.getElementById('volSold').textContent = vol.toFixed(2);
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>