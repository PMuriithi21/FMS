<?php
// supervisor/stock.php
require_once __DIR__ . '/../includes/config.php';
requireRole('supervisor');
$db = getDB();
$pageTitle = 'Stock Levels';
$activeNav = 'stock';
$stock = $db->query("SELECT f.fuel_name, f.unit_price, COALESCE(s.current_volume,0) as vol, s.last_updated FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
include __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-title">📦 Current Stock Levels</div>
    <div class="stats-grid">
        <?php while($row = $stock->fetch_assoc()):
            $low = $row['vol'] < 500;
        ?>
        <div style="text-align:center;">
            <div class="stat-bubble <?= $low ? 'danger' : 'navy' ?>" style="<?= $low ? 'border-color:var(--danger)' : '' ?>">
                <span class="stat-icon">⛽</span>
                <span class="stat-value"><?= number_format($row['vol'],0) ?></span>
                <span class="stat-label"><?= clean($row['fuel_name']) ?> (L)</span>
            </div>
            <?php if($low): ?><p style="color:var(--danger);font-size:12px;margin-top:8px;">⚠️ Low stock</p><?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
</div>
<div class="card">
    <div class="card-title">📋 Stock Detail</div>
    <div class="table-wrap">
        <?php
        $stock2 = $db->query("SELECT f.fuel_name, f.unit_price, COALESCE(s.current_volume,0) as vol, s.last_updated FROM fuel_types f LEFT JOIN stock s ON f.fuel_id=s.fuel_id WHERE f.status='active' ORDER BY f.fuel_name");
        ?>
        <table>
            <thead><tr><th>Fuel Type</th><th>Current Volume (L)</th><th>Unit Price (KES/L)</th><th>Estimated Value (KES)</th><th>Last Updated</th></tr></thead>
            <tbody>
                <?php while($row = $stock2->fetch_assoc()): ?>
                <tr>
                    <td>⛽ <?= clean($row['fuel_name']) ?></td>
                    <td style="font-weight:700;"><?= number_format($row['vol'],2) ?></td>
                    <td><?= number_format($row['unit_price'],2) ?></td>
                    <td style="color:var(--brown);font-weight:600;"><?= number_format($row['vol'] * $row['unit_price'],2) ?></td>
                    <td style="font-size:12px;color:var(--text-light);"><?= $row['last_updated'] ?? '—' ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>