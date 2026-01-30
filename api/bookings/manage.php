<?php
// api/bookings/manage.php
header('Content-Type: application/json');

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

// Method Override para Hostinger (no permite PUT/DELETE)
$requestMethod = $_SERVER['REQUEST_METHOD'];
if ($requestMethod === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_GET['_method'] ?? null;
    if ($override && in_array(strtoupper($override), ['PUT', 'DELETE'])) {
        $requestMethod = strtoupper($override);
    }
}

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
if (!$SECRET_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: JWT_SECRET missing']);
    exit;
}
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
if (!$currentUser) {
    $currentUser = ['username' => $user['username'] ?? 'Sistema'];
}

$id = $_GET['id'] ?? null;
$date = $_GET['date'] ?? null;

// READ
if ($requestMethod === 'GET') {
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
    echo json_encode([
        'error' => 'Access denied', 
        'reason' => 'Role not allowed',
        'current_role' => $user['role'],
        'required_roles' => ['ADMIN', 'VENTAS', 'LOGISTICA']
    ]);
    exit;
}

    // Helper for Webhook
    require_once __DIR__ . '/unmet_demand_helper.php';

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
    if ($requestMethod === 'POST' || $requestMethod === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        $fields = [];
        $params = [];
        
        $oldBooking = null;
        if ($requestMethod === 'PUT') {
             $id = $input['id'] ?? $id ?? null; // Prefer input ID if passed in body
             if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID needed']); exit; }
             $params[':id'] = $id;

             // Fetch Old State for Deviation/Unmet Demand Logic
             $stmtOld = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
             $stmtOld->execute([':id' => $id]);
             $oldBooking = $stmtOld->fetch(PDO::FETCH_ASSOC);
        }

        // Allowed fields
        $allowed = [
            'client', 'clientCode', 'orderNumber', 'description', 'kg', 'duration', 
            'color', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 
            'realStartTime', 'realEndTime', 'status', 'priority', 'observations', 
            'items', 'sampi_time', 'sampi_on', 'cancellation_reason'
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
        
        if ($requestMethod === 'POST') {
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
            $finalBooking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // --- LOGIC: Unmet Demand & Deviations (optional, tables may not exist) ---
            try {
                if ($oldBooking && $finalBooking) {
                    // 1. Check for Unmet Demand (Items changed)
                    $oldItems = json_decode($oldBooking['items'] ?? '[]', true);
                    $newItems = json_decode($finalBooking['items'] ?? '[]', true);
                    // Simple equality check to avoid expensive logic if unnecessary
                    if (json_encode($oldItems) !== json_encode($newItems)) {
                         registerUnmetDemand($pdo, $lastId, $oldItems, $newItems, 'modification', 'User modification', $user['id']);
                    }

                    // 2. Check for Logistic Deviations (Time/Door/Cancel)
                    registerLogisticDeviation($pdo, $lastId, $oldBooking, $finalBooking, $user['id']);

                    // 3. Special Case: Cancellation via Status Update
                    if ($finalBooking['status'] === 'CANCELLED' && $oldBooking['status'] !== 'CANCELLED') {
                         registerCancellationUnmetDemand($pdo, $lastId, $input['cancellation_reason'] ?? 'Cancelled by user', $user['id']);
                    }
                }
            } catch (Exception $e) {
                // Silently ignore - tables may not exist yet
                error_log("Unmet demand tracking skipped: " . $e->getMessage());
            }
            // ------------------------------------------

            syncToSheets($finalBooking, $requestMethod === 'POST' ? 'CREATED' : 'UPDATED', $currentUser['username'], $WEBHOOK_SHEETS);
            
            echo json_encode($finalBooking);

        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // DELETE
    if ($requestMethod === 'DELETE') {
        if (!$id) { http_response_code(400); exit; }
        try {
            // Fetch for webhook before delete
            $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            // --- LOGIC: Cancel Unmet Demand before delete (optional) ---
            try {
                if ($booking) {
                    registerCancellationUnmetDemand($pdo, $id, 'Booking Deleted', $user['id']);
                }
            } catch (Exception $e) {
                // Silently ignore - table may not exist
                error_log("Unmet demand tracking skipped: " . $e->getMessage());
            }
            // ------------------------------------------------

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
