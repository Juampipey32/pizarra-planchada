<?php
// api/products/manage.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

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

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Only ADMIN can modify/delete
if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo ADMIN puede modificar productos']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [':id' => $id];

    if (isset($input['code'])) {
        $fields[] = "code = :code";
        $params[':code'] = trim($input['code']);
    }
    if (isset($input['name'])) {
        $fields[] = "name = :name";
        $params[':name'] = trim($input['name']) ?: null;
    }
    if (isset($input['coefficient'])) {
        $fields[] = "coefficient = :coef";
        $params[':coef'] = floatval($input['coefficient']);
    }

    if (empty($fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $sql = "UPDATE Products SET " . implode(', ', $fields) . ", updatedAt = NOW() WHERE id = :id";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    try {
        $stmt = $pdo->prepare("DELETE FROM Products WHERE id = :id");
        $stmt->execute([':id' => $id]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
