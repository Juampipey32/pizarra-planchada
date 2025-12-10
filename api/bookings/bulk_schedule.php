<?php
// api/bookings/bulk_schedule.php
// Create multiple bookings from parsed Excel data
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (in_array($user['role'], ['VISUALIZADOR', 'INVITADO'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Read only access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$date = $payload['date'] ?? date('Y-m-d');
$orders = $payload['orders'] ?? [];

if (empty($orders)) {
    http_response_code(400);
    echo json_encode(['error' => 'No hay pedidos para programar']);
    exit;
}

ensureBookingColumns($pdo);

$created = [];
$skipped = [];

foreach ($orders as $order) {
    if (empty($order['startTime']) || empty($order['resourceId'])) {
        $skipped[] = ['orderNumber' => $order['orderNumber'] ?? null, 'reason' => 'Sin horario o recurso'];
        continue;
    }

    $timeParts = explode(':', $order['startTime']);
    if (count($timeParts) < 2) {
        $skipped[] = ['orderNumber' => $order['orderNumber'] ?? null, 'reason' => 'Hora inválida'];
        continue;
    }

    $startHour = (int)$timeParts[0];
    $startMinute = (int)$timeParts[1];
    $duration = isset($order['duration']) ? (int)$order['duration'] : suggestDurationMinutes($order['kg'] ?? 0);

    $conflictId = findOverlap($pdo, $date, $order['resourceId'], $startHour, $startMinute, $duration);
    if ($conflictId) {
        $skipped[] = ['orderNumber' => $order['orderNumber'] ?? null, 'reason' => 'Conflicto con otra carga', 'conflictId' => $conflictId];
        continue;
    }

    $params = [
        ':client' => $order['client'] ?? 'Sin cliente',
        ':description' => $order['description'] ?? '',
        ':kg' => isset($order['kg']) ? floatval($order['kg']) : 0,
        ':duration' => $duration,
        ':color' => $order['color'] ?? 'blue',
        ':resourceId' => $order['resourceId'],
        ':date' => $date,
        ':startTimeHour' => $startHour,
        ':startTimeMinute' => $startMinute,
        ':realStartTime' => null,
        ':realEndTime' => null,
        ':status' => 'PLANNED',
        ':createdBy' => $user['id']
    ];

    $fields = ['client', 'description', 'kg', 'duration', 'color', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 'realStartTime', 'realEndTime', 'status', 'createdBy', 'createdAt', 'updatedAt'];
    $placeholders = [':client', ':description', ':kg', ':duration', ':color', ':resourceId', ':date', ':startTimeHour', ':startTimeMinute', ':realStartTime', ':realEndTime', ':status', ':createdBy', 'NOW()', 'NOW()'];

    if (isset($order['clientCode'])) {
        $fields[] = 'clientCode';
        $placeholders[] = ':clientCode';
        $params[':clientCode'] = $order['clientCode'];
    }
    if (isset($order['orderNumber'])) {
        $fields[] = 'orderNumber';
        $placeholders[] = ':orderNumber';
        $params[':orderNumber'] = $order['orderNumber'];
    }

    $sql = "INSERT INTO Bookings (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute($params);
        $created[] = ['orderNumber' => $order['orderNumber'] ?? null, 'id' => $pdo->lastInsertId()];
    } catch (PDOException $e) {
        $skipped[] = ['orderNumber' => $order['orderNumber'] ?? null, 'reason' => $e->getMessage()];
    }
}

echo json_encode([
    'created' => $created,
    'skipped' => $skipped,
    'createdCount' => count($created),
    'skippedCount' => count($skipped)
]);
