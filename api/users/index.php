<?php
// api/users/index.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once './bootstrap.php';

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';
$ROLES = bootstrap_roles();
$ALLOWED_ROLES = $ROLES;

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($user['role'] !== 'ADMIN') {
    http_response_code(403);
    echo json_encode(['error' => 'Solo ADMIN puede gestionar usuarios']);
    exit;
}

ensure_users_schema($pdo, $ROLES);
ensure_admin_exists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, username, role, createdAt, updatedAt FROM Users ORDER BY id ASC");
    $users = $stmt->fetchAll();
    echo json_encode($users);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $role = strtoupper(trim($input['role'] ?? ''));

    if (!$username || !$password || !$role) {
        http_response_code(400);
        echo json_encode(['error' => 'Usuario, contraseña y rol son obligatorios']);
        exit;
    }

    if (!in_array($role, $ALLOWED_ROLES, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Rol inválido']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    try {
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (:u, :p, :r)");
        $stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role]);

        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT id, username, role, createdAt, updatedAt FROM Users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $created = $stmt->fetch();

        http_response_code(201);
        echo json_encode($created);
    } catch (PDOException $e) {
        if ($e->errorInfo[1] == 1062) {
            http_response_code(409);
            echo json_encode(['error' => 'El usuario ya existe']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
