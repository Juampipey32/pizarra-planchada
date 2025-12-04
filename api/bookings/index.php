<?php
// api/bookings/index.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';
$WEBHOOK_SHEETS = getenv('WEBHOOK_SHEETS');

// Auth Check
$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Ensure schema has new columns (idempotente)
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

// Helper for Webhook
function syncToSheets($booking, $action, $username, $webhookUrl) {
    if (!$webhookUrl) return;

    // Prepare payload similar to Node.js version
    $h = $booking['startTimeHour'];
    $m = $booking['startTimeMinute'];
    $timeLabel = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
    
    $priorityMap = [
        'blue' => 'Normal',
        'red' => 'Urgente',
        'green' => 'Lista',
        'orange' => 'Espera'
    ];

    $payload = array_merge($booking, [
        'startTimeHour' => $timeLabel,
        'resourceId' => $booking['resourceId'], // Simplified, assuming resourceId is the name or mapped elsewhere
        'color' => $priorityMap[$booking['color']] ?? $booking['color'],
        'startTime' => ['hour' => $h, 'minute' => $m, 'label' => $timeLabel],
        'resourceName' => $booking['resourceId'],
        'action' => $action,
        'updatedBy' => $username,
        'timestamp' => date('c')
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? date('Y-m-d'); // si no viene, usar hoy para evitar 400 y permitir trailing slash

    $stmt = $pdo->prepare("
        SELECT b.*, u.username as creator_username 
        FROM Bookings b 
        LEFT JOIN Users u ON b.createdBy = u.id 
        WHERE b.date = :date
    ");
    $stmt->execute([':date' => $date]);
    $bookings = $stmt->fetchAll();
    
    // Transform to match Node.js output structure if needed (e.g. creator object)
    $result = array_map(function($b) {
        $b['creator'] = ['username' => $b['creator_username']];
        unset($b['creator_username']);
        return $b;
    }, $bookings);

    echo json_encode($result);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureBookingColumns($pdo);

    if ($user['role'] === 'VISUALIZADOR') {
        http_response_code(403);
        echo json_encode(['error' => 'Read only access']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $availableCols = [];
    try {
        foreach ($pdo->query("DESCRIBE Bookings") as $row) {
            $availableCols[] = $row['Field'];
        }
    } catch (PDOException $e) {
        // continue
    }

    $params = [
        ':client' => $input['client'],
        ':description' => $input['description'] ?? '',
        ':kg' => isset($input['kg']) ? floatval($input['kg']) : 0,
        ':duration' => $input['duration'],
        ':color' => $input['color'] ?? 'blue',
        ':resourceId' => $input['resourceId'],
        ':date' => $input['date'],
        ':startTimeHour' => $input['startTimeHour'],
        ':startTimeMinute' => $input['startTimeMinute'],
        ':realStartTime' => $input['realStartTime'] ?? null,
        ':realEndTime' => $input['realEndTime'] ?? null,
        ':status' => $input['status'] ?? 'PLANNED',
        ':createdBy' => $user['id']
    ];

    $fields = ['client', 'description', 'kg', 'duration', 'color', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 'realStartTime', 'realEndTime', 'status', 'createdBy', 'createdAt', 'updatedAt'];
    $placeholders = [':client', ':description', ':kg', ':duration', ':color', ':resourceId', ':date', ':startTimeHour', ':startTimeMinute', ':realStartTime', ':realEndTime', ':status', ':createdBy', 'NOW()', 'NOW()'];

    if (empty($availableCols) || in_array('clientCode', $availableCols)) {
        $fields[] = 'clientCode';
        $placeholders[] = ':clientCode';
        $params[':clientCode'] = $input['clientCode'] ?? null;
    }
    if (empty($availableCols) || in_array('orderNumber', $availableCols)) {
        $fields[] = 'orderNumber';
        $placeholders[] = ':orderNumber';
        $params[':orderNumber'] = $input['orderNumber'] ?? null;
    }

    $sql = "INSERT INTO Bookings (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $pdo->prepare($sql);

    try {
        $stmt->execute($params);
        $id = $pdo->lastInsertId();
        
        // Fetch created booking
        $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();
        
        // Get username for webhook
        $stmtU = $pdo->prepare("SELECT username FROM Users WHERE id = :id");
        $stmtU->execute([':id' => $user['id']]);
        $creator = $stmtU->fetch();

        syncToSheets($booking, 'CREATED', $creator['username'], $WEBHOOK_SHEETS);
        
        echo json_encode($booking);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
