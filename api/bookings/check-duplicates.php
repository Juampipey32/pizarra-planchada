<?php
// api/bookings/check-duplicates.php
// Check for duplicate orders by order number in a date range
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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['orderNumbers']) || !is_array($input['orderNumbers'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Se requiere un array de orderNumbers']);
    exit;
}

$orderNumbers = array_filter($input['orderNumbers'], function($num) {
    return !empty($num) && $num !== 'N/A';
});

if (empty($orderNumbers)) {
    echo json_encode(['duplicates' => []]);
    exit;
}

// Date range: current week (Monday to Sunday)
$date = isset($input['date']) ? $input['date'] : date('Y-m-d');
$timestamp = strtotime($date);
$weekStart = date('Y-m-d', strtotime('monday this week', $timestamp));
$weekEnd = date('Y-m-d', strtotime('sunday this week', $timestamp));

try {
    // Build query with placeholders for IN clause
    $placeholders = implode(',', array_fill(0, count($orderNumbers), '?'));
    $sql = "
        SELECT orderNumber, client, clientCode, date,
               startTimeHour, startTimeMinute, resourceId, kg
        FROM Bookings
        WHERE orderNumber IN ($placeholders)
        AND date BETWEEN ? AND ?
        ORDER BY date, startTimeHour, startTimeMinute
    ";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $params = array_merge($orderNumbers, [$weekStart, $weekEnd]);
    $stmt->execute($params);

    $results = $stmt->fetchAll();

    // Group by order number
    $duplicates = [];
    foreach ($results as $row) {
        $orderNum = $row['orderNumber'];
        if (!isset($duplicates[$orderNum])) {
            $duplicates[$orderNum] = [];
        }
        $duplicates[$orderNum][] = [
            'client' => $row['client'],
            'clientCode' => $row['clientCode'],
            'date' => $row['date'],
            'time' => sprintf('%02d:%02d', $row['startTimeHour'], $row['startTimeMinute']),
            'resourceId' => $row['resourceId'],
            'kg' => $row['kg']
        ];
    }

    echo json_encode([
        'duplicates' => $duplicates,
        'weekRange' => [
            'start' => $weekStart,
            'end' => $weekEnd
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al verificar duplicados: ' . $e->getMessage()]);
}
