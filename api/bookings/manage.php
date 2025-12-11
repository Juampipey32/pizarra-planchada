<?php
// api/bookings/manage.php
header('Content-Type: application/json');

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: 'secret_key_change_me');
$WEBHOOK_SHEETS = defined('SHEET_WEBHOOK_URL') ? SHEET_WEBHOOK_URL : (getenv('WEBHOOK_SHEETS') ?: '');

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    if (defined('DEV_MODE') && DEV_MODE === true) {
        // Fallback for Dev Mode
        $user = ['id' => 1, 'username' => 'System (Dev)', 'role' => 'ADMIN'];
    } else {
        http_response_code(401); 
        echo json_encode(['error' => 'Unauthorized']); 
        exit;
    }
}

// Get current user username
$stmtU = $pdo->prepare("SELECT username FROM Users WHERE id = :id");
$stmtU->execute([':id' => $user['id']]);
$currentUser = $stmtU->fetch();

$id = $_GET['id'] ?? null;
$date = $_GET['date'] ?? null;

// READ
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();
        if ($booking) {
            echo json_encode($booking);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Not found']);
        }
    } else {
        // Filter by date or status
        // If status=PENDING, ignore date filter or handle separate queue
        $status = $_GET['status'] ?? null;
        
        $sql = "SELECT * FROM Bookings WHERE status != 'CANCELLED'";
        $params = [];

        if ($status === 'PENDING') {
            $sql .= " AND status = 'PENDING'";
        } else if ($date) {
            $sql .= " AND (date = :date OR status = 'PENDING')"; // Pendientes siempre visibles en drawer? No, maybe separate fetch.
            // Let's separate: If date is present, show all for that date + PENDING for any date?
            // Actually, PENDING usually has no confirmed date (or is for today/future). 
            // Better strategy: Front requests `?date=X` for board, and `?status=PENDING` for drawer.
            if (!isset($_GET['status'])) {
                 $sql .= " AND date = :date AND status != 'PENDING'"; // Board items
                 $params[':date'] = $date;
            }
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $bookings = $stmt->fetchAll();
        echo json_encode($bookings);
    }
    exit;
}

// WRITE (ADMIN/VENTAS)
if (!in_array($user['role'], ['ADMIN', 'VENTAS', 'LOGISTICA'])) { // Added LOGISTICA
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Helper for Webhook
function syncToSheets($booking, $action, $username, $webhookUrl) {
    if (!$webhookUrl) return;
    // Format payload as expected by Sheets
    $h = $booking['startTimeHour'];
    $m = $booking['startTimeMinute'];
    $timeLabel = str_pad($h, 2, '0', STR_PAD_LEFT) . ':' . str_pad($m, 2, '0', STR_PAD_LEFT);
    
    $payload = array_merge($booking, [
        'startTimeHour' => $timeLabel,
        'action' => $action,
        'updatedBy' => $username,
        'timestamp' => date('c')
    ]);
    
    // Fire and forget
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

// CREATE / UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [];
    
    if ($_SERVER['REQUEST_METHOD'] === 'PUT' && !$id) {
         $id = $input['id'] ?? null;
         if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID needed']); exit; }
         $params[':id'] = $id;
    }

    // Allowed fields
    $allowed = [
        'client', 'clientCode', 'orderNumber', 'description', 'kg', 'duration', 
        'color', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 
        'realStartTime', 'realEndTime', 'status', 'priority', 'observations', 
        'items', 'sampi_time', 'sampi_on'
    ];

    foreach ($input as $key => $value) {
        if (in_array($key, $allowed)) {
            $fields[] = "$key = :$key";
            // Json handling
            if ($key === 'items') {
                $params[":$key"] = is_array($value) ? json_encode($value) : $value;
            } else {
                 $params[":$key"] = $value;
            }
        }
    }
    
    // Auto status update if pending moved to board
    if (isset($input['resourceId']) && $input['resourceId'] !== 'PENDIENTE') {
        // If it was pending, now it's PLANNED (unless specified otherwise)
        if (!isset($input['status']) || $input['status'] === 'PENDING') {
             $fields[] = "status = 'PLANNED'";
        }
    }

    $fields[] = "updatedAt = NOW()";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fields[] = "createdBy = :createdBy";
        $params[':createdBy'] = $user['id'];
        
        $sql = "INSERT INTO Bookings SET " . implode(', ', $fields);
    } else {
        $sql = "UPDATE Bookings SET " . implode(', ', $fields) . " WHERE id = :id";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $lastId = $id ? $id : $pdo->lastInsertId();
        
        // Fetch full record for webhook
        $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $lastId]);
        $finalBooking = $stmt->fetch();
        
        syncToSheets($finalBooking, $_SERVER['REQUEST_METHOD'] === 'POST' ? 'CREATED' : 'UPDATED', $currentUser['username'], $WEBHOOK_SHEETS);
        
        echo json_encode($finalBooking);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

// DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (!$id) { http_response_code(400); exit; }
    try {
        // Fetch for webhook before delete
        $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();

        $stmt = $pdo->prepare("DELETE FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $id]);
        
        if ($booking) {
             syncToSheets($booking, 'DELETED', $currentUser['username'], $WEBHOOK_SHEETS);
        }
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>