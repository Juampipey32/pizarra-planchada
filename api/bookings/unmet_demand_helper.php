<?php
/**
 * Unmet Demand Helper
 * Functions to automatically detect and register unmet demand
 */

/**
 * Register unmet demand when items are reduced or removed
 *
 * @param PDO $pdo Database connection
 * @param int $bookingId Booking ID
 * @param array $originalItems Original items before edit
 * @param array $newItems New items after edit
 * @param string $reason Reason code
 * @param string $reasonDetail Optional detailed reason
 * @param int $userId User who made the change
 * @return int Number of unmet demand records created
 */
function registerUnmetDemand($pdo, $bookingId, $originalItems, $newItems, $reason, $reasonDetail = null, $userId = null) {
    // Get booking info
    $stmt = $pdo->prepare("SELECT client, clientCode, orderNumber, date FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return 0;
    }

    // Convert arrays to associative by code
    $originalByCode = [];
    foreach ($originalItems as $item) {
        $code = $item['code'] ?? '';
        if ($code) {
            $originalByCode[$code] = $item;
        }
    }

    $newByCode = [];
    foreach ($newItems as $item) {
        $code = $item['code'] ?? '';
        if ($code) {
            $newByCode[$code] = $item;
        }
    }

    $recordsCreated = 0;

    // Check each original item
    foreach ($originalByCode as $code => $originalItem) {
        $originalQty = floatval($originalItem['qty'] ?? 0);
        $originalKg = floatval($originalItem['kg'] ?? 0);

        $finalQty = 0;
        $finalKg = 0;

        // Check if item still exists
        if (isset($newByCode[$code])) {
            $finalQty = floatval($newByCode[$code]['qty'] ?? 0);
            $finalKg = floatval($newByCode[$code]['kg'] ?? 0);
        }

        // Calculate unmet
        $unmetQty = $originalQty - $finalQty;
        $unmetKg = $originalKg - $finalKg;

        // Only register if there's unmet demand
        if ($unmetQty > 0 || $unmetKg > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO UnmetDemand (
                    booking_id, client, clientCode, orderNumber,
                    product_code, product_name,
                    original_qty, final_qty, unmet_qty,
                    original_kg, final_kg, unmet_kg,
                    reason, reason_detail, date, created_by, created_at
                ) VALUES (
                    :booking_id, :client, :clientCode, :orderNumber,
                    :product_code, :product_name,
                    :original_qty, :final_qty, :unmet_qty,
                    :original_kg, :final_kg, :unmet_kg,
                    :reason, :reason_detail, :date, :created_by, NOW()
                )
            ");

            $stmt->execute([
                ':booking_id' => $bookingId,
                ':client' => $booking['client'],
                ':clientCode' => $booking['clientCode'],
                ':orderNumber' => $booking['orderNumber'],
                ':product_code' => $code,
                ':product_name' => $originalItem['name'] ?? $originalItem['desc'] ?? "Producto $code",
                ':original_qty' => $originalQty,
                ':final_qty' => $finalQty,
                ':unmet_qty' => $unmetQty,
                ':original_kg' => $originalKg,
                ':final_kg' => $finalKg,
                ':unmet_kg' => $unmetKg,
                ':reason' => $reason,
                ':reason_detail' => $reasonDetail,
                ':date' => $booking['date'],
                ':created_by' => $userId
            ]);

            $recordsCreated++;
        }
    }

    return $recordsCreated;
}

/**
 * Register unmet demand when entire booking is cancelled
 *
 * @param PDO $pdo Database connection
 * @param int $bookingId Booking ID
 * @param string $reasonDetail Cancellation reason
 * @param int $userId User who cancelled
 * @return int Number of unmet demand records created
 */
function registerCancellationUnmetDemand($pdo, $bookingId, $reasonDetail = null, $userId = null) {
    // Get booking info
    $stmt = $pdo->prepare("SELECT client, clientCode, orderNumber, date, items FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $bookingId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        return 0;
    }

    $items = json_decode($booking['items'], true) ?? [];

    if (empty($items)) {
        return 0;
    }

    $recordsCreated = 0;

    foreach ($items as $item) {
        $code = $item['code'] ?? '';
        $qty = floatval($item['qty'] ?? 0);
        $kg = floatval($item['kg'] ?? 0);

        if ($qty > 0 || $kg > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO UnmetDemand (
                    booking_id, client, clientCode, orderNumber,
                    product_code, product_name,
                    original_qty, final_qty, unmet_qty,
                    original_kg, final_kg, unmet_kg,
                    reason, reason_detail, date, created_by, created_at
                ) VALUES (
                    :booking_id, :client, :clientCode, :orderNumber,
                    :product_code, :product_name,
                    :original_qty, 0, :unmet_qty,
                    :original_kg, 0, :unmet_kg,
                    'cancelled', :reason_detail, :date, :created_by, NOW()
                )
            ");

            $stmt->execute([
                ':booking_id' => $bookingId,
                ':client' => $booking['client'],
                ':clientCode' => $booking['clientCode'],
                ':orderNumber' => $booking['orderNumber'],
                ':product_code' => $code,
                ':product_name' => $item['name'] ?? $item['desc'] ?? "Producto $code",
                ':original_qty' => $qty,
                ':unmet_qty' => $qty,
                ':original_kg' => $kg,
                ':unmet_kg' => $kg,
                ':reason_detail' => $reasonDetail,
                ':date' => $booking['date'],
                ':created_by' => $userId
            ]);

            $recordsCreated++;
        }
    }

    return $recordsCreated;
}

/**
 * Register logistic deviation (delay, door change, etc)
 *
 * @param PDO $pdo Database connection
 * @param int $bookingId Booking ID
 * @param array $originalBooking Original booking data
 * @param array $updatedBooking Updated booking data
 * @param int $userId User who made the change
 * @return bool Success
 */
function registerLogisticDeviation($pdo, $bookingId, $originalBooking, $updatedBooking, $userId = null) {
    $deviations = [];

    // Check for time deviations (realStartTime vs planned)
    if (!empty($updatedBooking['realStartTime']) && !empty($originalBooking['startTimeHour'])) {
        $plannedStart = sprintf('%02d:%02d:00', $originalBooking['startTimeHour'], $originalBooking['startTimeMinute']);
        $realStart = $updatedBooking['realStartTime'];

        $plannedTimestamp = strtotime($plannedStart);
        $realTimestamp = strtotime($realStart);
        $deviationMinutes = round(($realTimestamp - $plannedTimestamp) / 60);

        if (abs($deviationMinutes) > 5) { // More than 5 minutes difference
            $deviationType = $deviationMinutes > 0 ? 'delay' : 'early';
            $impactLevel = abs($deviationMinutes) > 30 ? 'high' : (abs($deviationMinutes) > 15 ? 'medium' : 'low');

            $deviations[] = [
                'type' => $deviationType,
                'planned_start_time' => $plannedStart,
                'real_start_time' => $realStart,
                'deviation_minutes' => $deviationMinutes,
                'impact_level' => $impactLevel
            ];
        }
    }

    // Check for door changes
    if (!empty($originalBooking['resourceId']) && !empty($updatedBooking['resourceId']) &&
        $originalBooking['resourceId'] !== $updatedBooking['resourceId']) {

        $deviations[] = [
            'type' => 'door_change',
            'planned_door' => $originalBooking['resourceId'],
            'real_door' => $updatedBooking['resourceId'],
            'impact_level' => 'medium'
        ];
    }

    // Check for cancellation
    if ($updatedBooking['status'] === 'CANCELLED' && $originalBooking['status'] !== 'CANCELLED') {
        $deviations[] = [
            'type' => 'cancellation',
            'impact_level' => 'critical',
            'reason' => $updatedBooking['cancellation_reason'] ?? null
        ];
    }

    // Insert deviations
    foreach ($deviations as $deviation) {
        $stmt = $pdo->prepare("
            INSERT INTO LogisticDeviations (
                booking_id, deviation_type,
                planned_start_time, real_start_time,
                deviation_minutes,
                planned_door, real_door,
                reason, impact_level, date, created_by, created_at
            ) VALUES (
                :booking_id, :deviation_type,
                :planned_start_time, :real_start_time,
                :deviation_minutes,
                :planned_door, :real_door,
                :reason, :impact_level, :date, :created_by, NOW()
            )
        ");

        $stmt->execute([
            ':booking_id' => $bookingId,
            ':deviation_type' => $deviation['type'],
            ':planned_start_time' => $deviation['planned_start_time'] ?? null,
            ':real_start_time' => $deviation['real_start_time'] ?? null,
            ':deviation_minutes' => $deviation['deviation_minutes'] ?? null,
            ':planned_door' => $deviation['planned_door'] ?? null,
            ':real_door' => $deviation['real_door'] ?? null,
            ':reason' => $deviation['reason'] ?? null,
            ':impact_level' => $deviation['impact_level'],
            ':date' => $updatedBooking['date'] ?? $originalBooking['date'],
            ':created_by' => $userId
        ]);
    }

    return count($deviations) > 0;
}
?>
