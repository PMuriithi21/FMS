<?php
// manager/payroll.php
require_once __DIR__ . '/../includes/config.php';
requireRole('manager');
$db = getDB();
$pageTitle = 'Supervisor Payroll Report';
$activeNav = 'payroll';

$month = $_GET['month'] ?? date('Y-m');
[$year, $mon] = explode('-', $month);

// Shifts per supervisor with hours worked
$stmt = $db->prepare("
    SELECT u.user_id, u.name,
        COUNT(s.shift_id) as total_shifts,
        COUNT(CASE WHEN s.status='closed' THEN 1 END) as closed_shifts,
        COALESCE(SUM(CASE WHEN s.status='closed' THEN TIMESTAMPDIFF(MINUTE, s.created_at, s.closed_at) END),0) / 60 as hours_worked,
        COALESCE(SUM(t.volume_sold),0) as total_vol,
        COALESCE(SUM(t.amount_paid),0) as total_rev
    FROM users u
    LEFT JOIN shifts s ON u.user_id=s.user_id AND YEAR(s.shift_date)=? AND MONTH(s.shift_date)=?
    LEFT JOIN transactions t ON u.user_id=t.user_id AND YEAR(t.trans_date)=? AND MONTH(t.trans_date)=?
    WHERE u.role='supervisor' AND u.status='active'
    GROUP BY u.user_id, u.name
    ORDER BY u.name
");
$stmt->bind_param('iiii', $year, $mon, $year, $mon);
$stmt->execute();
$payroll = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">💰 Supervisor Payroll Report</div>
    <form method="GET" style="display:flex;gap:12px;align-items:center;margin-bottom:24px;">
        <label style="font-size:13px;font-weight:600;color:var(--text-mid);">Month:</label>
        <input type="month" name="month" value="<?= clean($month) ?>" class="form-control" style="width:180px;"/>
        <button type="submit" class="btn btn-brown btn-sm">Generate</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Supervisor</th>
                    <th>Total Shifts</th>
                    <th>Closed Shifts</th>
                    <th>Hours Worked</th>
                    <th>Volume Sold (L)</th>
                    <th>Revenue (KES)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payroll)): ?>
                <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:20px;">No supervisor data found.</td></tr>
                <?php endif; ?>
                <?php foreach($payroll as $row): ?>
                <tr>
                    <td style="font-weight:600;">👷 <?= clean($row['name']) ?></td>
                    <td><?= $row['total_shifts'] ?></td>
                    <td><?= $row['closed_shifts'] ?></td>
                    <td style="font-weight:700;color:var(--navy);"><?= number_format($row['hours_worked'],1) ?> hrs</td>
                    <td><?= number_format($row['total_vol'],2) ?></td>
                    <td style="font-weight:700;color:var(--brown);"><?= number_format($row['total_rev'],2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p style="font-size:12px;color:var(--text-light);margin-top:16px;">* Hours worked calculated from shift open to close time. Open shifts are excluded.</p>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>