<?php
// api/bookings/real-start.php
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
    echo json_encode(['error' => 'Solo ADMIN puede marcar hora real']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$timestamp = $input['real_start_at'] ?? null; // ISO 8601

if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID requerido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, real_start_at FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        http_response_code(404);
        echo json_encode(['error' => 'Pedido no encontrado']);
        exit;
    }

    $realStart = $timestamp ?: date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("UPDATE Bookings SET real_start_at = :real_start_at, updatedAt = NOW() WHERE id = :id");
    $stmt->execute([
        ':real_start_at' => $realStart,
        ':id' => $id
    ]);

    $stmt = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $updated = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'booking' => $updated]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
