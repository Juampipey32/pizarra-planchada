<?php
// api/bookings/block.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
if (!$SECRET_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: JWT_SECRET missing']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
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

if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo ADMIN puede bloquear pedidos']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$blockedBy = trim($input['blocked_by'] ?? '');
$amount = $input['blocked_debt_amount'] ?? null;
$reason = $input['blocked_reason'] ?? null;

$allowedBlockedBy = ['JUAMPI', 'MAURICIO', 'SANDRA'];

if (!$id || !$blockedBy || $amount === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos obligatorios']);
    exit;
}

if (!in_array(strtoupper($blockedBy), $allowedBlockedBy, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Bloqueante inválido']);
    exit;
}

if (!is_numeric($amount) || floatval($amount) <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Monto de deuda inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido no encontrado']);
        exit;
    }

    if (!empty($booking['is_blocked'])) {
        http_response_code(409);
        echo json_encode(['error' => 'El pedido ya está bloqueado']);
        exit;
    }

    $prevStatus = $booking['status'] ?? 'PENDING';
    $prevResourceId = $booking['resourceId'] ?? null;
    $prevColor = $booking['color'] ?? 'blue';

    $stmt = $pdo->prepare("UPDATE Bookings SET
        is_blocked = 1,
        blocked_by = :blocked_by,
        blocked_reason = :blocked_reason,
        blocked_debt_amount = :blocked_debt_amount,
        blocked_at = NOW(),
        prev_status = :prev_status,
        prev_resourceId = :prev_resourceId,
        status = 'PENDING',
        resourceId = 'PENDIENTE',
        color = 'red',
        prev_color = :prev_color,
        updatedAt = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':blocked_by' => strtoupper($blockedBy),
        ':blocked_reason' => $reason,
        ':blocked_debt_amount' => floatval($amount),
        ':prev_status' => $prevStatus,
        ':prev_resourceId' => $prevResourceId,
        ':prev_color' => $prevColor,
        ':id' => $id
    ]);

    $stmtAudit = $pdo->prepare("INSERT INTO BookingBlockAudit
        (booking_id, action, blocked_by, amount, reason, actor_user_id)
        VALUES (:booking_id, 'BLOCK', :blocked_by, :amount, :reason, :actor_user_id)
    ");
    $stmtAudit->execute([
        ':booking_id' => $id,
        ':blocked_by' => strtoupper($blockedBy),
        ':amount' => floatval($amount),
        ':reason' => $reason,
        ':actor_user_id' => $user['id']
    ]);

    $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'booking' => $updated]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
