<?php
// manager/monthly.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/notification_helper.php';
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireRole('manager');
$db = getDB();
$uid = $_SESSION['user_id'];

if (isset($_GET['read'])) {
    markNotificationAsRead((int)$_GET['read']);
}
$pageTitle = 'Monthly Sales Report';
$activeNav = 'monthly';

$month = $_GET['month'] ?? date('Y-m');
[$year, $mon] = explode('-', $month);

$emailSuccess = '';
$emailError = '';

// Get this manager's own email as default recipient
$um = $db->prepare("SELECT email, name FROM users WHERE user_id=?");
$um->bind_param('i', $uid);
$um->execute();
$meRow = $um->get_result()->fetch_assoc();
$um->close();
$myEmail = $meRow['email'] ?? '';

// =====================================================
// SEND TO EMAIL
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_email') {

    $recipientEmail = trim($_POST['recipient_email'] ?? '');
    $sendMonth = trim($_POST['send_month'] ?? $month);

    if (!$recipientEmail || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {

        $emailError = 'Please enter a valid email address.';

    } else {

        [$sy, $sm] = explode('-', $sendMonth);

        // Rebuild report data for the month being sent
        $stmt = $db->prepare("SELECT f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev, COUNT(*) as shifts FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.fuel_id ORDER BY f.fuel_name");
        $stmt->bind_param('ii', $sy, $sm);
        $stmt->execute();
        $sendByFuel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt2 = $db->prepare("SELECT t.trans_date, f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.trans_date, t.fuel_id ORDER BY t.trans_date DESC");
        $stmt2->bind_param('ii', $sy, $sm);
        $stmt2->execute();
        $sendDaily = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt2->close();

        $sendTotalVol = array_sum(array_column($sendByFuel, 'vol'));
        $sendTotalRev = array_sum(array_column($sendByFuel, 'rev'));
        $sendMonthLabel = date('F Y', strtotime($sendMonth . '-01'));

        // ---- Build PDF HTML ----
        ob_start();
        ?>
        <html>
        <head>
        <style>
            body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
            h1 { font-size: 18px; color: #1a2744; margin-bottom: 4px; }
            .subtitle { font-size: 12px; color: #666; margin-bottom: 20px; }
            .stats { width: 100%; margin-bottom: 20px; }
            .stats td { width: 33%; text-align: center; padding: 10px; border: 1px solid #ddd; }
            .stat-label { font-size: 10px; color: #666; text-transform: uppercase; }
            .stat-value { font-size: 16px; font-weight: bold; color: #1a2744; }
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
            <div class="subtitle">Monthly Sales Report — <?= clean($sendMonthLabel) ?></div>

            <table class="stats">
                <tr>
                    <td><div class="stat-value"><?= number_format($sendTotalVol,0) ?> L</div><div class="stat-label">Total Volume</div></td>
                    <td><div class="stat-value">KES <?= number_format($sendTotalRev,0) ?></div><div class="stat-label">Total Revenue</div></td>
                    <td><div class="stat-value"><?= count(array_unique(array_column($sendDaily,'trans_date'))) ?></div><div class="stat-label">Active Days</div></td>
                </tr>
            </table>

            <h2>Summary by Fuel Type</h2>
            <table class="data">
                <thead><tr><th>Fuel Type</th><th>Volume Sold (L)</th><th>Revenue (KES)</th><th>Shifts</th></tr></thead>
                <tbody>
                    <?php foreach($sendByFuel as $row): ?>
                    <tr>
                        <td>⛽ <?= clean($row['fuel_name']) ?></td>
                        <td><?= number_format($row['vol'],2) ?></td>
                        <td><?= number_format($row['rev'],2) ?></td>
                        <td><?= $row['shifts'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row">
                        <td>TOTAL</td>
                        <td><?= number_format($sendTotalVol,2) ?> L</td>
                        <td>KES <?= number_format($sendTotalRev,2) ?></td>
                        <td>—</td>
                    </tr>
                </tbody>
            </table>

            <h2>Daily Breakdown</h2>
            <table class="data">
                <thead><tr><th>Date</th><th>Fuel</th><th>Volume (L)</th><th>Revenue (KES)</th></tr></thead>
                <tbody>
                    <?php foreach($sendDaily as $row): ?>
                    <tr>
                        <td><?= clean($row['trans_date']) ?></td>
                        <td>⛽ <?= clean($row['fuel_name']) ?></td>
                        <td><?= number_format($row['vol'],2) ?></td>
                        <td><?= number_format($row['rev'],2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="footer">Generated on <?= date('d M Y, H:i') ?> — Fuel Management System</div>
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

        $pdfOutput = $dompdf->output();

        // Save to a temp file so PHPMailer can attach it
        $tmpPath = sys_get_temp_dir() . '/monthly_report_' . uniqid() . '.pdf';
        file_put_contents($tmpPath, $pdfOutput);

        $emailBody = "
            <p>Hello,</p>
            <p>Please find attached the Monthly Sales Report for <strong>" . clean($sendMonthLabel) . "</strong>.</p>
            <p><strong>Total Volume:</strong> " . number_format($sendTotalVol,2) . " L<br>
            <strong>Total Revenue:</strong> KES " . number_format($sendTotalRev,2) . "</p>
            <p>Regards,<br>Fuel Management System</p>
        ";

        $sent = sendReportEmail(
            $recipientEmail,
            $recipientEmail,
            "Monthly Sales Report - " . $sendMonthLabel,
            $emailBody,
            $tmpPath,
            "Monthly_Report_" . $sendMonth . ".pdf"
        );

        @unlink($tmpPath);

        if ($sent) {
            $emailSuccess = "Report sent successfully to $recipientEmail.";
        } else {
            $emailError = "Failed to send email. Please check mail server settings.";
        }
    }
}

// By fuel
$stmt = $db->prepare("SELECT f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev, COUNT(*) as shifts FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.fuel_id ORDER BY f.fuel_name");
$stmt->bind_param('ii', $year, $mon);
$stmt->execute();
$byFuel = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Daily breakdown
$stmt2 = $db->prepare("SELECT t.trans_date, f.fuel_name, SUM(t.volume_sold) as vol, SUM(t.amount_paid) as rev FROM transactions t JOIN fuel_types f ON t.fuel_id=f.fuel_id WHERE YEAR(t.trans_date)=? AND MONTH(t.trans_date)=? GROUP BY t.trans_date, t.fuel_id ORDER BY t.trans_date DESC");
$stmt2->bind_param('ii', $year, $mon);
$stmt2->execute();
$daily = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt2->close();

$totalVol = array_sum(array_column($byFuel, 'vol'));
$totalRev = array_sum(array_column($byFuel, 'rev'));

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">📆 Monthly Sales Report</div>

    <?php if ($emailSuccess): ?>
    <div class="alert alert-success"><?= clean($emailSuccess) ?></div>
    <?php endif; ?>
    <?php if ($emailError): ?>
    <div class="alert alert-error"><?= clean($emailError) ?></div>
    <?php endif; ?>

    <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:16px;flex-wrap:wrap;">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">Month:</label>
        <input type="month" name="month" value="<?= clean($month) ?>" class="form-control" style="width:180px;"/>
        <button type="submit" class="btn btn-brown btn-sm">View Report</button>

        <a href="monthly_pdf.php?month=<?= urlencode($month) ?>" class="btn btn-primary btn-sm" target="_blank" style="text-decoration:none;">
            📄 Download PDF
        </a>
    </form>

    <form method="POST" style="display:flex;gap:12px;align-items:center;margin-bottom:24px;flex-wrap:wrap;background:var(--nude-pale);padding:14px;border-radius:8px;">
        <input type="hidden" name="action" value="send_email">
        <input type="hidden" name="send_month" value="<?= clean($month) ?>">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">📧 Send this report to:</label>
        <input type="email" name="recipient_email" value="<?= clean($myEmail) ?>" class="form-control" style="width:240px;" placeholder="email@example.com" required>
        <button type="submit" class="btn btn-brown btn-sm">Send Email</button>
    </form>

    <div class="stats-grid" style="margin-bottom:28px;">
        <div style="text-align:center;">
            <div class="stat-bubble navy">
                <span class="stat-icon">🛢</span>
                <span class="stat-value"><?= number_format($totalVol,0) ?>L</span>
                <span class="stat-label">Total Volume</span>
            </div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble brown">
                <span class="stat-icon">💰</span>
                <span class="stat-value">KES <?= number_format($totalRev,0) ?></span>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>
        <div style="text-align:center;">
            <div class="stat-bubble nude">
                <span class="stat-icon">📅</span>
                <span class="stat-value"><?= count(array_unique(array_column($daily,'trans_date'))) ?></span>
                <span class="stat-label">Active Days</span>
            </div>
        </div>
    </div>

    <h3 style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:12px;">Summary by Fuel Type</h3>
    <div class="table-wrap" style="margin-bottom:24px;">
        <table>
            <thead><tr><th>Fuel Type</th><th>Volume Sold (L)</th><th>Revenue (KES)</th><th>Shifts</th></tr></thead>
            <tbody>
                <?php if (empty($byFuel)): ?>
                <tr><td colspan="4" style="text-align:center;color:var(--text-light);padding:20px;">No data for <?= clean($month) ?>.</td></tr>
                <?php endif; ?>
                <?php foreach($byFuel as $row): ?>
                <tr>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-weight:700;"><?= number_format($row['vol'],2) ?></td>
                    <td style="color:var(--brown);font-weight:700;"><?= number_format($row['rev'],2) ?></td>
                    <td><?= $row['shifts'] ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (!empty($byFuel)): ?>
                <tr style="background:var(--nude-pale);font-weight:800;">
                    <td>TOTAL</td>
                    <td><?= number_format($totalVol,2) ?> L</td>
                    <td style="color:var(--navy);">KES <?= number_format($totalRev,2) ?></td>
                    <td>—</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <h3 style="font-size:14px;font-weight:700;color:var(--navy);margin-bottom:12px;">Daily Breakdown</h3>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Fuel</th><th>Volume (L)</th><th>Revenue (KES)</th></tr></thead>
            <tbody>
                <?php foreach($daily as $row): ?>
                <tr>
                    <td><?= clean($row['trans_date']) ?></td>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td><?= number_format($row['vol'],2) ?></td>
                    <td style="color:var(--brown);"><?= number_format($row['rev'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>