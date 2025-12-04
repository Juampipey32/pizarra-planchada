<?php
// api/bookings/manage.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';
$WEBHOOK_SHEETS = getenv('WEBHOOK_SHEETS');

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

// Ensure columns exist (idempotente)
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
        error_log("ensureBookingColumns manage error: " . $e->getMessage());
    }
}

ensureBookingColumns($pdo);

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Fetch existing booking
$stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
$stmt->execute([':id' => $id]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// Helper for Webhook (Duplicated for simplicity, ideally in a shared file)
function syncToSheets($booking, $action, $username, $webhookUrl) {
    if (!$webhookUrl) return;
    $h = $booking['startTimeHour'];
    $m = $booking['startTimeMinute'];
    $timeLabel = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $priorityMap = ['blue' => 'Normal', 'red' => 'Urgente', 'green' => 'Lista', 'orange' => 'Espera'];
    $payload = array_merge($booking, [
        'startTimeHour' => $timeLabel,
        'resourceId' => $booking['resourceId'],
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

// Get current user username
$stmtU = $pdo->prepare("SELECT username FROM Users WHERE id = :id");
$stmtU->execute([':id' => $user['id']]);
$currentUser = $stmtU->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Build dynamic update query
    $fields = [];
    $params = [':id' => $id];
    
    foreach ($input as $key => $value) {
        if (in_array($key, ['client', 'clientCode', 'orderNumber', 'description', 'kg', 'duration', 'color', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 'realStartTime', 'realEndTime', 'status'])) {
            $fields[] = "$key = :$key";
            $params[":$key"] = ($key === 'kg') ? floatval($value) : $value;
        }
    }
    $fields[] = "updatedAt = NOW()";
    
    if (empty($fields)) {
        echo json_encode($booking);
        exit;
    }

    $sql = "UPDATE Bookings SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute($params);
        
        // Fetch updated
        $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $updatedBooking = $stmt->fetch();
        
        syncToSheets($updatedBooking, 'UPDATED', $currentUser['username'], $WEBHOOK_SHEETS);
        
        echo json_encode($updatedBooking);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $stmt = $pdo->prepare("DELETE FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        syncToSheets($booking, 'DELETED', $currentUser['username'], $WEBHOOK_SHEETS);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>
