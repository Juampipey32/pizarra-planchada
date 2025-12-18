<?php
// Shared helpers for booking operations

function ensureBookingColumns($pdo) {
    try {
        $cols = [];
        foreach ($pdo->query("DESCRIBE Bookings") as $row) {
            $cols[] = $row['Field'];
        }
        if (!in_array('clientCode', $cols)) {
            $pdo->exec("ALTER TABLE Bookings ADD COLUMN clientCode VARCHAR(50) NULL");
        }
        if (!in_array('orderNumber', $cols)) {
            $pdo->exec("ALTER TABLE Bookings ADD COLUMN orderNumber VARCHAR(50) NULL");
        }
        if (in_array('kg', $cols)) {
            $pdo->exec("ALTER TABLE Bookings MODIFY kg DECIMAL(10,3) DEFAULT 0");
        }
    } catch (PDOException $e) {
        error_log("ensureBookingColumns error: " . $e->getMessage());
    }
}

function normalizeDuration($duration) {
    $d = (int)$duration;
    if ($d <= 0) return 30;
    return $d;
}

function suggestDurationMinutes($kgTotal) {
    if (!$kgTotal || $kgTotal <= 0) return 30;
    $blocks = max(1, ceil($kgTotal / 1500));
    return $blocks * 30;
}

function findOverlap($pdo, $date, $resourceId, $startHour, $startMinute, $duration, $excludeId = null) {
    $startMinutes = ((int)$startHour * 60) + (int)$startMinute;
    $endMinutes = $startMinutes + normalizeDuration($duration);

    if (!$resourceId || $startMinutes < 0 || $endMinutes <= $startMinutes) {
        return null;
    }

    $sql = "SELECT id, startTimeHour, startTimeMinute, duration FROM Bookings WHERE date = :date AND resourceId = :resourceId";
    $params = [':date' => $date, ':resourceId' => $resourceId];
    if ($excludeId) {
        $sql .= " AND id != :excludeId";
        $params[':excludeId'] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $existingStart = ((int)$row['startTimeHour'] * 60) + (int)$row['startTimeMinute'];
        $existingEnd = $existingStart + normalizeDuration($row['duration']);

        $overlaps = !($endMinutes <= $existingStart || $startMinutes >= $existingEnd);
        if ($overlaps) {
            return $row['id'];
        }
    }

    return null;
}
