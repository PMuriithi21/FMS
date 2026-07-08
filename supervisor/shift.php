<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';

requireRole('supervisor');
$db = getDB();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');
$pageTitle = "Shift Entry";
$activeNav = "shift";
$success = "";
$error = "";

// =====================================
// CHECK IF SHIFT EXISTS (open shift only)
// =====================================

$shift = null;
$stmt = $db->prepare("
SELECT *
FROM shifts
WHERE user_id=?
AND shift_date=?
AND status='open'
ORDER BY shift_id DESC
LIMIT 1
");

$stmt->bind_param("is",$uid,$today);
$stmt->execute();
$shift = $stmt->get_result()->fetch_assoc();
$stmt->close();

// =====================================
// START SHIFT
// =====================================

if(
    $_SERVER['REQUEST_METHOD']=="POST"
    &&
    ($_POST['action'] ?? "")=="start_shift"
){
    if($shift){
        $error="Today's shift already exists.";
    }else{
        $stmt=$db->prepare("
        INSERT INTO shifts
        (
            user_id,
            shift_date,
            opening_meter,
            status
        )
        VALUES
        (
            ?,
            ?,
            0,
            'open'
        )
        ");
        $stmt->bind_param(
            "is",
            $uid,
            $today
        );
        $stmt->execute();
        $shift_id=$stmt->insert_id;
        $stmt->close();

        // =============================
        // Create empty records
        // for every active fuel
        // =============================

        $fuels=$db->query("
        SELECT *
        FROM fuel_types
        WHERE status='active'
        ORDER BY fuel_name
        ");

        while($fuel=$fuels->fetch_assoc()){
            $sale=$db->prepare("
            INSERT INTO shift_sales
            (
                shift_id,
                fuel_id,
                opening_meter,
                closing_meter,
                volume_sold,
                amount
            )
            VALUES
            (
                ?,
                ?,
                0,
                0,
                0,
                0
            )
            ");

            $sale->bind_param(
                "ii",
                $shift_id,
                $fuel['fuel_id']
            );

            $sale->execute();
            $sale->close();

        }

        header("Location: shift.php");
        exit();
    }

}

// =====================================
// RELOAD SHIFT (open shift only)
// =====================================

$stmt=$db->prepare("
SELECT *
FROM shifts
WHERE user_id=?
AND shift_date=?
AND status='open'
ORDER BY shift_id DESC
LIMIT 1
");

$stmt->bind_param(
"is",
$uid,
$today
);

$stmt->execute();
$shift=$stmt->get_result()->fetch_assoc();
$stmt->close();

// =====================================
// SAVE SHIFT
// =====================================

if (
    $_SERVER['REQUEST_METHOD'] == "POST"
    &&
    ($_POST['action'] ?? "") == "save_shift"
) {

    $fuelIds = $_POST['fuel_id'];
    $opening = $_POST['opening_meter'];
    $closing = $_POST['closing_meter'];
    $prices  = $_POST['price'];

    $cash  = (double)$_POST['cash'];
    $mpesa = (double)$_POST['mpesa'];

    $expectedTotal = 0;

    $db->begin_transaction();

    try {

        for ($i = 0; $i < count($fuelIds); $i++) {

            $fuel_id = (int)$fuelIds[$i];

            $open = (double)$opening[$i];

            $close = (double)$closing[$i];

            $price = (double)$prices[$i];

            if ($close < $open) {

                throw new Exception(
                    "Closing meter cannot be less than opening meter."
                );

            }

            $litres = $close - $open;

            $expected = $litres * $price;

            $expectedTotal += $expected;

            $stmt = $db->prepare("
                UPDATE shift_sales
                SET

                opening_meter=?,

                closing_meter=?,

                volume_sold=?,

                amount=?

                WHERE shift_id=?

                AND fuel_id=?
            ");

            $stmt->bind_param(

                "ddddii",

                $open,

                $close,

                $litres,

                $expected,

                $shift['shift_id'],

                $fuel_id

            );

            $stmt->execute();

            $stmt->close();

        }

        $received = $cash + $mpesa;

        $variance = $received - $expectedTotal;

        $stmt = $db->prepare("

            UPDATE shifts

            SET

            cash_at_hand=?,

            mpesa=?,

            expected_amount=?,

            variance=?

            WHERE shift_id=?

        ");

        $stmt->bind_param(

            "ddddi",

            $cash,

            $mpesa,

            $expectedTotal,

            $variance,

            $shift['shift_id']

        );

        $stmt->execute();

        $stmt->close();

        $db->commit();

        $success = "Shift saved successfully.";

        // Reload shift so page shows fresh totals
        $stmt = $db->prepare("
        SELECT *
        FROM shifts
        WHERE shift_id=?
        LIMIT 1
        ");
        $stmt->bind_param("i", $shift['shift_id']);
        $stmt->execute();
        $shift = $stmt->get_result()->fetch_assoc();
        $stmt->close();

    }

    catch(Exception $e){

        $db->rollback();

        $error = $e->getMessage();

    }

}

// =====================================
// CLOSE SHIFT
// =====================================

if (
    $_SERVER['REQUEST_METHOD'] == "POST"
    &&
    ($_POST['action'] ?? "") == "close_shift"
) {

    $db->begin_transaction();

    try {

        // Reload all fuel sales for this shift
            $stmt = $db->prepare("
    SELECT ss.*, f.fuel_name
    FROM shift_sales ss
    JOIN fuel_types f ON ss.fuel_id = f.fuel_id
    WHERE ss.shift_id=?
");
        $stmt->bind_param(
            "i",
            $shift['shift_id']
        );

        $stmt->execute();

        $sales = $stmt->get_result();

        while($sale = $sales->fetch_assoc()){

            // -------------------------
            // Insert transaction record
            // -------------------------

            $tr = $db->prepare("
                INSERT INTO transactions
                (
                    shift_id,
                    user_id,
                    fuel_id,
                    opening_meter,
                    closing_meter,
                    volume_sold,
                    amount_paid,
                    trans_date
                )
                VALUES
                (
                    ?,?,?,?,?,?,?,?
                )
            ");

            $tr->bind_param(

                "iiidddds",

                $shift['shift_id'],

                $uid,

                $sale['fuel_id'],

                $sale['opening_meter'],

                $sale['closing_meter'],

                $sale['volume_sold'],

                $sale['amount'],

                $today

            );

            $tr->execute();

            $tr->close();


            // -------------------------
            // Deduct Stock (with insufficient-stock guard)
            // -------------------------

            $stk = $db->prepare("
                UPDATE stock

                SET current_volume =
                current_volume - ?

                WHERE fuel_id=?

                AND current_volume >= ?
            ");

            $stk->bind_param(

                "did",

                $sale['volume_sold'],

                $sale['fuel_id'],

                $sale['volume_sold']

            );

            $stk->execute();

            if ($db->affected_rows === 0) {
throw new Exception(
    "Insufficient stock to deduct " .
    number_format($sale['volume_sold'], 2) .
    " L of " . $sale['fuel_name'] . "."
);
            }

            $stk->close();


            // -------------------------
            // Check remaining stock
            // -------------------------

            $check = $db->prepare("
                SELECT

                s.current_volume,

                f.fuel_name

                FROM stock s

                JOIN fuel_types f

                ON s.fuel_id=f.fuel_id

                WHERE s.fuel_id=?
            ");

            $check->bind_param(
                "i",
                $sale['fuel_id']
            );

            $check->execute();

            $stock = $check->get_result()->fetch_assoc();

            $check->close();


            // -------------------------
            // LOW STOCK ALERT
            // -------------------------

            if($stock['current_volume'] <= 500){

                createNotification(

                    "⚠ Low Stock",

                    $stock['fuel_name'] .
                    " has only " .
                    number_format(
                        $stock['current_volume'],
                        2
                    ) .
                    " L remaining.",

                    "admin",

                    "low_stock",

                    "danger"

                );

                createNotification(

                    "⚠ Low Stock",

                    $stock['fuel_name'] .
                    " has only " .
                    number_format(
                        $stock['current_volume'],
                        2
                    ) .
                    " L remaining.",

                    "manager",

                    "low_stock",

                    "danger"

                );

            }

        }


        // -------------------------
        // Close shift
        // -------------------------

        $stmt = $db->prepare("
            UPDATE shifts

            SET

            status='closed',

            closed_at=NOW()

            WHERE shift_id=?
        ");

        $stmt->bind_param(

            "i",

            $shift['shift_id']

        );

        $stmt->execute();

        $stmt->close();

        // -------------------------
        // Report notification
        // -------------------------
        createNotification(
            "📋 Daily Report Updated",
            "A supervisor has completed today's shift.",
            "manager",
            "report",
            "info"
        );
        createNotification(
            "📋 Daily Report Updated",
            "A supervisor has completed today's shift.",
            "admin",
            "report",
            "info"
        );

        // -------------------------
        // Future SMS Hook
        // -------------------------
        /*
        sendDailySMSReport(
            $shift['shift_id']
        );
        */

        $db->commit();

        $success =
        "✅ Shift closed successfully.";

        header("Location: shift.php");
        exit();
    }
    catch(Exception $e){
        $db->rollback();
        $error = $e->getMessage();
    }
}

// =====================================
// LOAD FUELS
// =====================================

$fuelSales=[];
if($shift){
$stmt=$db->prepare("
SELECT
ss.*,
f.fuel_name,
f.unit_price
FROM shift_sales ss
JOIN fuel_types f
ON ss.fuel_id=f.fuel_id
WHERE ss.shift_id=?
ORDER BY f.fuel_name
");

$stmt->bind_param(
"i",
$shift['shift_id']
);
$stmt->execute();
$fuelSales=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

}

include __DIR__.'/../includes/header.php';
?>
<?php if($success): ?>
<div class="alert alert-success">
<?= $success ?>
</div>
<?php endif; ?>

<?php if($error): ?>
<div class="alert alert-error">
<?= clean($error) ?>
</div>

<?php endif; ?>

<div class="card">

    <div class="card-title">
        ⛽ Today's Shift
    </div>

<?php if(!$shift): ?>

    <div style="padding:30px;text-align:center;">

        <p style="margin-bottom:20px;">
            No shift has been started today.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="start_shift">

            <button class="btn btn-primary">

                ▶ Start Shift

            </button>

        </form>

    </div>

<?php else: ?>

<?php
$receivedNow = (float)$shift['cash_at_hand'] + (float)$shift['mpesa'];
$varianceNow = $receivedNow - (float)$shift['expected_amount'];
?>

<form method="POST" id="shiftForm">

<input
type="hidden"
name="action"
value="save_shift">

<div class="table-wrap">

<table>

<thead>

<tr>

<th>Fuel</th>

<th>Opening Meter</th>

<th>Closing Meter</th>

<th>Litres Sold</th>

<th>Price/Litre</th>

<th>Expected (KES)</th>

</tr>

</thead>

<tbody>

<?php foreach($fuelSales as $fuel): ?>

<tr>

<td>

<strong>

<?= clean($fuel['fuel_name']) ?>

</strong>

<input
type="hidden"
name="fuel_id[]"
value="<?= $fuel['fuel_id'] ?>">

</td>

<td>

<input

type="number"

step="0.01"

class="form-control opening"

name="opening_meter[]"

value="<?= $fuel['opening_meter'] ?>"

oninput="recalcRow(this)"

>

</td>

<td>

<input

type="number"

step="0.01"

class="form-control closing"

name="closing_meter[]"

value="<?= $fuel['closing_meter'] ?>"

oninput="recalcRow(this)"

>

</td>

<td>

<input

type="text"

readonly

class="form-control litres"

value="<?= number_format($fuel['volume_sold'],2) ?>"

>

</td>

<td>

<input

type="text"

readonly

class="form-control price"

value="<?= number_format($fuel['unit_price'],2) ?>"

>

<input

type="hidden"

name="price[]"

value="<?= $fuel['unit_price'] ?>"

>

</td>

<td>

<input

type="text"

readonly

class="form-control expected"

value="<?= number_format($fuel['amount'],2) ?>"

>

</td>

</tr>

<?php endforeach; ?>

</tbody>

</table>

</div>

<hr style="margin:35px 0;">

<div class="grid-2">

<div class="form-group">

<label>

💵 Cash at Hand

</label>

<input

type="number"

step="0.01"

name="cash"

id="cash"

class="form-control"

value="<?= $shift['cash_at_hand'] ?>"

oninput="recalcTotals()">

</div>

<div class="form-group">

<label>

📱 M-Pesa

</label>

<input

type="number"

step="0.01"

name="mpesa"

id="mpesa"

class="form-control"

value="<?= $shift['mpesa'] ?>"

oninput="recalcTotals()">

</div>

</div>

<div class="grid-3">

<div class="form-group">

<label>

Expected Total

</label>

<input

readonly

id="expected_total"

class="form-control"

value="<?= number_format($shift['expected_amount'],2) ?>">

</div>

<div class="form-group">

<label>

Received

</label>

<input

readonly

id="received"

class="form-control"

value="<?= number_format($receivedNow,2) ?>">

</div>

<div class="form-group">

<label>

Variance

</label>

<input

readonly

id="variance"

class="form-control"

value="<?= number_format($varianceNow,2) ?>">

</div>

</div>

<br>

<button

class="btn btn-primary btn-block">

💾 Save Shift

</button>

</form>

<form method="POST" style="margin-top:15px;" onsubmit="return confirm('Close this shift? This cannot be undone.');">
<input type="hidden" name="action" value="close_shift">
<button class="btn btn-brown btn-block">🏁 Close Shift</button>
</form>

<?php endif; ?>

</div>

<script>
function recalcRow(el) {
    const row = el.closest('tr');
    const opening = parseFloat(row.querySelector('.opening').value || 0);
    const closing = parseFloat(row.querySelector('.closing').value || 0);
    const price = parseFloat(row.querySelector('.price').value || 0);

    const litres = Math.max(0, closing - opening);
    const expected = litres * price;

    row.querySelector('.litres').value = litres.toFixed(2);
    row.querySelector('.expected').value = expected.toFixed(2);

    recalcTotals();
}

function recalcTotals() {
    let expectedTotal = 0;

    document.querySelectorAll('.expected').forEach(function(el) {
        expectedTotal += parseFloat(el.value || 0);
    });

    const cash = parseFloat(document.getElementById('cash').value || 0);
    const mpesa = parseFloat(document.getElementById('mpesa').value || 0);
    const received = cash + mpesa;
    const variance = received - expectedTotal;

    document.getElementById('expected_total').value = expectedTotal.toFixed(2);
    document.getElementById('received').value = received.toFixed(2);
    document.getElementById('variance').value = variance.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.opening, .closing').forEach(function(input) {
        recalcRow(input);
    });
});
</script>