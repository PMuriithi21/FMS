<?php

require_once __DIR__ . '/notification_helper.php';

function checkLowStock($fuelID)
{
    $db = getDB();

    $stmt = $db->prepare("
        SELECT
            fuel_name,
            current_volume,
            low_stock_alert_sent
        FROM stock
        JOIN fuel_types USING(fuel_id)
        WHERE fuel_id = ?
    ");

    $stmt->bind_param("i", $fuelID);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return;
    }

    // Stock is low
    if ($row['current_volume'] < 500) {

        // Only send notification once
        if ($row['low_stock_alert_sent'] == 0) {

            createNotification(
                "⚠️ Low Stock",
                $row['fuel_name'] . " has only " . $row['current_volume'] . " L remaining.",
                "manager"
            );

            createNotification(
                "⚠️ Low Stock",
                $row['fuel_name'] . " has only " . $row['current_volume'] . " L remaining.",
                "admin"
            );

            $update = $db->prepare("
                UPDATE stock
                SET low_stock_alert_sent = 1
                WHERE fuel_id = ?
            ");

            $update->bind_param("i", $fuelID);
            $update->execute();
            $update->close();
        }

    } else {

        // Stock has recovered
        if ($row['low_stock_alert_sent'] == 1) {

            $update = $db->prepare("
                UPDATE stock
                SET low_stock_alert_sent = 0
                WHERE fuel_id = ?
            ");

            $update->bind_param("i", $fuelID);
            $update->execute();
            $update->close();
        }
    }
}