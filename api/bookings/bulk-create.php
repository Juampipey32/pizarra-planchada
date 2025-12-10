<?php
// api/bookings/bulk-create.php
// Create multiple bookings with overlap validation
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: 'secret_key_change_me');

// Auth
$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($user['role'] === 'VISUALIZADOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Read only access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['bookings']) || !is_array($input['bookings'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere un array de bookings']);
    exit;
}

$bookings = $input['bookings'];
$date = $input['date'] ?? date('Y-m-d');

// Ensure columns exist
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

ensureBookingColumns($pdo);

/**
 * Check if a booking overlaps with existing bookings
 * Returns array of overlapping bookings or empty array if no conflicts
 */
function checkOverlap($pdo, $resourceId, $date, $startHour, $startMinute, $duration) {
    // Calculate end time
    $startMinutes = $startHour * 60 + $startMinute;
    $endMinutes = $startMinutes + $duration;

    // Fetch existing bookings for this resource and date
    $stmt = $pdo->prepare("
        SELECT id, client, clientCode, orderNumber, startTimeHour, startTimeMinute, duration, kg
        FROM Bookings
        WHERE resourceId = :resourceId
        AND date = :date
    ");
    $stmt->execute([
        ':resourceId' => $resourceId,
        ':date' => $date
    ]);
    $existing = $stmt->fetchAll();

    $overlaps = [];
    foreach ($existing as $booking) {
        $existingStart = $booking['startTimeHour'] * 60 + $booking['startTimeMinute'];
        $existingEnd = $existingStart + $booking['duration'];

        // Check if intervals overlap
        if ($startMinutes < $existingEnd && $endMinutes > $existingStart) {
            $overlaps[] = [
                'id' => $booking['id'],
                'client' => $booking['client'],
                'clientCode' => $booking['clientCode'],
                'orderNumber' => $booking['orderNumber'],
                'startTime' => sprintf('%02d:%02d', $booking['startTimeHour'], $booking['startTimeMinute']),
                'duration' => $booking['duration'],
                'kg' => $booking['kg']
            ];
        }
    }

    return $overlaps;
}

/**
 * Auto-split Sampi logic
 * Returns array with 'split' => bool and 'bookings' => array of bookings to create
 */
function autoSplitSampi($booking, $items) {
    $SAMPI_CODES = ['1011', '1015', '1016'];
    $SAMPI_THRESHOLD = 648;

    $sampiProducts = array_filter($items, function($item) use ($SAMPI_CODES) {
        return in_array($item['code'], $SAMPI_CODES);
    });

    $sampiKg = array_reduce($sampiProducts, function($sum, $item) {
        return $sum + $item['kg'];
    }, 0);

    if ($sampiKg > $SAMPI_THRESHOLD) {
        // Split into two bookings
        $regularProducts = array_filter($items, function($item) use ($SAMPI_CODES) {
            return !in_array($item['code'], $SAMPI_CODES);
        });

        $regularKg = array_reduce($regularProducts, function($sum, $item) {
            return $sum + $item['kg'];
        }, 0);

        $sampiDuration = calculateDuration($sampiKg);
        $regularDuration = calculateDuration($regularKg);

        return [
            'split' => true,
            'bookings' => [
                // Regular booking on original resource
                array_merge($booking, [
                    'kg' => round($regularKg, 2),
                    'duration' => $regularDuration,
                    'description' => ($booking['description'] ?? '') . ' [Productos regulares]'
                ]),
                // Sampi booking
                array_merge($booking, [
                    'resourceId' => 'sampi',
                    'kg' => round($sampiKg, 2),
                    'duration' => $sampiDuration,
                    'description' => ($booking['description'] ?? '') . ' [Productos Sampi: ' . implode(', ', array_column($sampiProducts, 'code')) . ']'
                ])
            ]
        ];
    }

    return ['split' => false, 'bookings' => [$booking]];
}

function calculateDuration($kg) {
    $KG_PER_HOUR = 2000;
    $hoursNeeded = $kg / $KG_PER_HOUR;
    $minutesNeeded = ceil(($hoursNeeded * 60) / 30) * 30;
    return max($minutesNeeded, 30);
}

try {
    $pdo->beginTransaction();

    $created = [];
    $errors = [];
    $warnings = [];

    foreach ($bookings as $idx => $booking) {
        // Validate required fields
        if (empty($booking['client']) || !isset($booking['resourceId']) ||
            !isset($booking['startTimeHour']) || !isset($booking['startTimeMinute']) ||
            !isset($booking['duration'])) {
            $errors[] = [
                'index' => $idx,
                'booking' => $booking,
                'error' => 'Faltan campos requeridos'
            ];
            continue;
        }

        // Check for overlaps
        $overlaps = checkOverlap(
            $pdo,
            $booking['resourceId'],
            $date,
            $booking['startTimeHour'],
            $booking['startTimeMinute'],
            $booking['duration']
        );

        if (!empty($overlaps)) {
            $errors[] = [
                'index' => $idx,
                'booking' => $booking,
                'error' => 'Solapamiento detectado',
                'overlaps' => $overlaps
            ];
            continue;
        }

        // Auto-split Sampi if needed
        $items = $booking['items'] ?? [];
        $splitResult = autoSplitSampi($booking, $items);

        foreach ($splitResult['bookings'] as $bookingToCreate) {
            // Check overlap for Sampi booking too if split
            if ($splitResult['split'] && $bookingToCreate['resourceId'] === 'sampi') {
                $sampiOverlaps = checkOverlap(
                    $pdo,
                    'sampi',
                    $date,
                    $bookingToCreate['startTimeHour'],
                    $bookingToCreate['startTimeMinute'],
                    $bookingToCreate['duration']
                );

                if (!empty($sampiOverlaps)) {
                    $errors[] = [
                        'index' => $idx,
                        'booking' => $booking,
                        'error' => 'Solapamiento en Sampi detectado',
                        'overlaps' => $sampiOverlaps
                    ];
                    continue 2; // Skip both bookings
                }
            }

            // Insert booking
            $params = [
                ':client' => $bookingToCreate['client'],
                ':clientCode' => $bookingToCreate['clientCode'] ?? null,
                ':orderNumber' => $bookingToCreate['orderNumber'] ?? null,
                ':description' => $bookingToCreate['description'] ?? '',
                ':kg' => floatval($bookingToCreate['kg'] ?? 0),
                ':duration' => intval($bookingToCreate['duration']),
                ':color' => $bookingToCreate['color'] ?? 'blue',
                ':resourceId' => $bookingToCreate['resourceId'],
                ':date' => $date,
                ':startTimeHour' => intval($bookingToCreate['startTimeHour']),
                ':startTimeMinute' => intval($bookingToCreate['startTimeMinute']),
                ':realStartTime' => null,
                ':realEndTime' => null,
                ':status' => 'PLANNED',
                ':createdBy' => $user['id']
            ];

            $sql = "INSERT INTO Bookings (
                client, clientCode, orderNumber, description, kg, duration, color,
                resourceId, date, startTimeHour, startTimeMinute,
                realStartTime, realEndTime, status, createdBy, createdAt, updatedAt
            ) VALUES (
                :client, :clientCode, :orderNumber, :description, :kg, :duration, :color,
                :resourceId, :date, :startTimeHour, :startTimeMinute,
                :realStartTime, :realEndTime, :status, :createdBy, NOW(), NOW()
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $id = $pdo->lastInsertId();
            $created[] = [
                'id' => $id,
                'originalIndex' => $idx,
                'resourceId' => $bookingToCreate['resourceId'],
                'client' => $bookingToCreate['client'],
                'orderNumber' => $bookingToCreate['orderNumber'] ?? null,
                'kg' => $bookingToCreate['kg']
            ];
        }

        if ($splitResult['split']) {
            $warnings[] = [
                'index' => $idx,
                'message' => 'Pedido dividido automáticamente: Puerta + Sampi',
                'booking' => $booking
            ];
        }
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'created' => $created,
        'errors' => $errors,
        'warnings' => $warnings,
        'summary' => [
            'total' => count($bookings),
            'created' => count($created),
            'errors' => count($errors),
            'warnings' => count($warnings)
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Error al crear bookings: ' . $e->getMessage()]);
}
