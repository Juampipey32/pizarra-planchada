<?php
// api/clients/index.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once __DIR__ . '/../bookings/helpers.php';

header('Content-Type: application/json');

ensureClientsSchema($pdo);

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
if (!$SECRET_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: JWT_SECRET missing']);
    exit;
}

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);
if (!$user) {
    if (defined('DEV_MODE') && DEV_MODE === true) {
        $user = ['id' => 1, 'username' => 'System (Dev)', 'role' => 'ADMIN'];
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM Clients ORDER BY clientCode");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['role'] !== 'ADMIN') {
        http_response_code(403);
        echo json_encode(['error' => 'Solo ADMIN puede modificar clientes']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $clientCode = trim($input['clientCode'] ?? '');
    $clientName = trim($input['clientName'] ?? '');
    $blocked = isset($input['blocked']) ? (int)!empty($input['blocked']) : 0;
    $blockedAmount = isset($input['blocked_amount']) ? floatval($input['blocked_amount']) : null;
    $blockedReason = $input['blocked_reason'] ?? null;

    if (!$clientCode) {
        http_response_code(400);
        echo json_encode(['error' => 'clientCode requerido']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO Clients (clientCode, clientName, blocked, blocked_amount, blocked_reason, blocked_at)
            VALUES (:code, :name, :blocked, :amount, :reason, :blocked_at)
            ON DUPLICATE KEY UPDATE
                clientName = VALUES(clientName),
                blocked = VALUES(blocked),
                blocked_amount = VALUES(blocked_amount),
                blocked_reason = VALUES(blocked_reason),
                blocked_at = VALUES(blocked_at)");

        $stmt->execute([
            ':code' => $clientCode,
            ':name' => $clientName ?: null,
            ':blocked' => $blocked,
            ':amount' => $blockedAmount,
            ':reason' => $blockedReason,
            ':blocked_at' => $blocked ? date('Y-m-d H:i:s') : null
        ]);

        // Apply to existing bookings for this client
        if ($blocked) {
            $update = $pdo->prepare("UPDATE Bookings SET
                is_blocked = 1,
                blocked_by = 'CLIENT',
                blocked_reason = :reason,
                blocked_debt_amount = :amount,
                blocked_at = NOW(),
                status = 'BLOCKED',
                resourceId = 'PENDIENTE',
                color = 'red',
                updatedAt = NOW()
                WHERE clientCode = :code");
            $update->execute([
                ':code' => $clientCode,
                ':reason' => $blockedReason,
                ':amount' => $blockedAmount
            ]);
        } else {
            $update = $pdo->prepare("UPDATE Bookings SET
                is_blocked = 0,
                blocked_by = NULL,
                blocked_reason = NULL,
                blocked_debt_amount = NULL,
                blocked_at = NULL,
                status = 'PENDING',
                resourceId = 'PENDIENTE',
                color = 'blue',
                updatedAt = NOW()
                WHERE clientCode = :code");
            $update->execute([':code' => $clientCode]);
        }

        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
