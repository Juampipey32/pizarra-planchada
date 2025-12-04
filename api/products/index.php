<?php
// api/products/index.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM Products ORDER BY code");
    echo json_encode($stmt->fetchAll());
    exit;
}

// From here on, only VENDEDOR can write
if ($user['role'] !== 'VENDEDOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo VENDEDOR puede modificar productos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $code = trim($input['code'] ?? '');
    $coefficient = isset($input['coefficient']) ? floatval($input['coefficient']) : null;
    $name = trim($input['name'] ?? '');

    if (!$code || $coefficient === null) {
        http_response_code(400);
        echo json_encode(['error' => 'code y coefficient son requeridos']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO Products (code, name, coefficient) VALUES (:code, :name, :coef)
            ON DUPLICATE KEY UPDATE name = VALUES(name), coefficient = VALUES(coefficient)");
        $stmt->execute([':code' => $code, ':name' => $name ?: null, ':coef' => $coefficient]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
