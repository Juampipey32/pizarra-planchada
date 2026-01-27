<?php
/**
 * Sampi Helpers - Sistema de cálculo de pallets Sampi V2
 *
 * Lógica:
 * - Productos Sampi se transportan por pallets automáticamente
 * - Cada pallet tarda 4 minutos fijos
 * - Se calcula: pallets = ceil(unidades / unidades_por_pallet)
 * - Tiempo = pallets × 4 minutos
 */

/**
 * Get Sampi configuration from database
 * Returns array of code => units_per_pallet
 */
function getSampiConfig($pdo) {
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    try {
        $stmt = $pdo->query("SELECT code, units_per_pallet FROM SampiPalletConfig WHERE active = 1");
        $config = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $config[$row['code']] = intval($row['units_per_pallet']);
        }

        // Fallback to hardcoded values if table doesn't exist or is empty
        if (empty($config)) {
            $config = [
                '1011' => 864,
                '1014' => 864,
                '1015' => 864,
                '1016' => 864,
                '1059' => 200,
                '1063' => 192,
                '1066' => 240
            ];
        }

        $cache = $config;
        return $cache;
    } catch (PDOException $e) {
        // Fallback if table doesn't exist yet
        return [
            '1011' => 864,
            '1014' => 864,
            '1015' => 864,
            '1016' => 864,
            '1059' => 200,
            '1063' => 192,
            '1066' => 240
        ];
    }
}

/**
 * Check if a product code is a Sampi product
 */
function isSampiProduct($code, $pdo) {
    $config = getSampiConfig($pdo);
    return isset($config[$code]);
}

/**
 * Calculate Sampi time and pallet details from items
 *
 * @param array $items Array of items with 'code' and 'qty'
 * @param PDO $pdo Database connection
 * @return array ['totalMinutes' => int, 'totalPallets' => int, 'detail' => array]
 */
function calculateSampiTime($items, $pdo) {
    $config = getSampiConfig($pdo);
    $MINUTES_PER_PALLET = 4;

    $totalPallets = 0;
    $detail = [];

    foreach ($items as $item) {
        $code = $item['code'] ?? '';
        $qty = intval($item['qty'] ?? 0);

        if (!isset($config[$code]) || $qty <= 0) {
            continue;
        }

        $unitsPerPallet = $config[$code];
        $pallets = max(1, ceil($qty / $unitsPerPallet));
        $minutes = $pallets * $MINUTES_PER_PALLET;

        $totalPallets += $pallets;

        $detail[$code] = [
            'units' => $qty,
            'units_per_pallet' => $unitsPerPallet,
            'pallets' => $pallets,
            'minutes' => $minutes
        ];
    }

    $totalMinutes = $totalPallets * $MINUTES_PER_PALLET;

    return [
        'totalMinutes' => $totalMinutes,
        'totalPallets' => $totalPallets,
        'detail' => $detail,
        'hasSampi' => !empty($detail)
    ];
}

/**
 * Split booking into regular and Sampi bookings
 * Returns array with 'split' => bool and 'bookings' => array
 */
function autoSplitSampi($booking, $items, $pdo) {
    $config = getSampiConfig($pdo);

    // Separate Sampi and regular products
    $sampiProducts = [];
    $regularProducts = [];

    foreach ($items as $item) {
        $code = $item['code'] ?? '';
        if (isset($config[$code])) {
            $sampiProducts[] = $item;
        } else {
            $regularProducts[] = $item;
        }
    }

    // If no Sampi products, return original booking
    if (empty($sampiProducts)) {
        return ['split' => false, 'bookings' => [$booking]];
    }

    // Calculate Sampi time
    $sampiCalc = calculateSampiTime($sampiProducts, $pdo);

    // Calculate regular products weight
    $regularKg = 0;
    foreach ($regularProducts as $item) {
        $regularKg += floatval($item['kg'] ?? 0);
    }

    $bookings = [];

    // Create regular booking if there are non-Sampi products
    if (!empty($regularProducts) && $regularKg > 0) {
        $regularDuration = calculateDuration($regularKg);
        $bookings[] = array_merge($booking, [
            'kg' => round($regularKg, 2),
            'duration' => $regularDuration,
            'items' => json_encode($regularProducts),
            'sampi_on' => 0,
            'sampi_time' => null,
            'sampi_pallets' => null,
            'description' => ($booking['description'] ?? '') . ' [Productos regulares]'
        ]);
    }

    // Create Sampi booking
    if ($sampiCalc['hasSampi']) {
        // Calculate total kg for Sampi products (for display purposes)
        $sampiKg = 0;
        foreach ($sampiProducts as $item) {
            $sampiKg += floatval($item['kg'] ?? 0);
        }

        $bookings[] = array_merge($booking, [
            'resourceId' => 'sampi',
            'kg' => round($sampiKg, 2),
            'duration' => $sampiCalc['totalMinutes'],
            'items' => json_encode($sampiProducts),
            'sampi_on' => 1,
            'sampi_time' => $sampiCalc['totalMinutes'],
            'sampi_pallets' => json_encode($sampiCalc['detail']),
            'description' => ($booking['description'] ?? '') . sprintf(
                ' [Sampi: %d pallets, %d min]',
                $sampiCalc['totalPallets'],
                $sampiCalc['totalMinutes']
            )
        ]);
    }

    return [
        'split' => count($bookings) > 1,
        'bookings' => $bookings
    ];
}

/**
 * Calculate duration for regular products (non-Sampi)
 * Based on weight: 1500 kg per 30-minute block
 */
function calculateDuration($kg) {
    if (!$kg || $kg <= 0) {
        return 30;
    }
    $blocks = max(1, ceil($kg / 1500));
    return $blocks * 30;
}

/**
 * Get or create accumulated Sampi booking for a given date
 * Returns the booking ID or creates a new one
 */
function getOrCreateSampiAccumulator($pdo, $date) {
    // Check if accumulator exists for this date
    $stmt = $pdo->prepare("
        SELECT id, duration, sampi_pallets
        FROM Bookings
        WHERE resourceId = 'sampi'
        AND date = :date
        AND client = 'SAMPI ACUMULADO'
        LIMIT 1
    ");
    $stmt->execute([':date' => $date]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        return [
            'id' => $existing['id'],
            'duration' => intval($existing['duration']),
            'pallets' => json_decode($existing['sampi_pallets'], true) ?? []
        ];
    }

    // Create new accumulator
    $stmt = $pdo->prepare("
        INSERT INTO Bookings (
            client, description, resourceId, date,
            startTimeHour, startTimeMinute, duration, kg,
            status, sampi_on, sampi_time, sampi_pallets, createdAt, updatedAt
        ) VALUES (
            'SAMPI ACUMULADO',
            'Acumulado automático de productos Sampi del día',
            'sampi',
            :date,
            4, 0, 0, 0,
            'PLANNED', 1, 0, '{}', NOW(), NOW()
        )
    ");
    $stmt->execute([':date' => $date]);

    return [
        'id' => $pdo->lastInsertId(),
        'duration' => 0,
        'pallets' => []
    ];
}

/**
 * Accumulate Sampi time to the daily accumulator
 */
function accumulateSampiTime($pdo, $date, $sampiDetail) {
    $accumulator = getOrCreateSampiAccumulator($pdo, $date);

    $currentPallets = $accumulator['pallets'];
    $totalMinutes = $accumulator['duration'];

    // Merge details
    foreach ($sampiDetail as $code => $detail) {
        if (isset($currentPallets[$code])) {
            $currentPallets[$code]['units'] += $detail['units'];
            $currentPallets[$code]['pallets'] += $detail['pallets'];
            $currentPallets[$code]['minutes'] += $detail['minutes'];
        } else {
            $currentPallets[$code] = $detail;
        }
        $totalMinutes += $detail['minutes'];
    }

    // Update accumulator
    $stmt = $pdo->prepare("
        UPDATE Bookings
        SET duration = :duration,
            sampi_time = :sampiTime,
            sampi_pallets = :sampiPallets,
            updatedAt = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':duration' => $totalMinutes,
        ':sampiTime' => $totalMinutes,
        ':sampiPallets' => json_encode($currentPallets),
        ':id' => $accumulator['id']
    ]);

    return [
        'accumulator_id' => $accumulator['id'],
        'total_minutes' => $totalMinutes,
        'detail' => $currentPallets
    ];
}
