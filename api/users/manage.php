<?php
// api/users/manage.php
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
    echo json_encode(['error' => 'Solo ADMIN puede gestionar usuarios']);
    exit;
}

ensure_users_schema($pdo, $ROLES);
ensure_admin_exists($pdo);

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Prevent deleting or downgrading the last admin
function count_admins($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM Users WHERE role = 'ADMIN'");
    $row = $stmt->fetch();
    return intval($row['c'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [':id' => $id];

    if (isset($input['username'])) {
        $fields[] = 'username = :username';
        $params[':username'] = trim($input['username']);
    }

    if (isset($input['role'])) {
        $role = strtoupper(trim($input['role']));
        if (!in_array($role, $ALLOWED_ROLES, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rol inválido']);
            exit;
        }
        if ($role !== 'ADMIN' && $id == $user['id'] && count_admins($pdo) <= 1) {
            http_response_code(400);
            echo json_encode(['error' => 'No puedes quitar tu propio rol ADMIN si eres el último admin']);
            exit;
        }
        $fields[] = 'role = :role';
        $params[':role'] = $role;
    }

    if (!empty($input['password'])) {
        $fields[] = 'password = :password';
        $params[':password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    if (empty($fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $sql = 'UPDATE Users SET ' . implode(', ', $fields) . ', updatedAt = NOW() WHERE id = :id';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($id == $user['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'No puedes eliminar tu propio usuario']);
        exit;
    }

    // avoid deleting last admin
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();
    if (!$target) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }
    if ($target['role'] === 'ADMIN' && count_admins($pdo) <= 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Debe existir al menos un ADMIN']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM Users WHERE id = :id');
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

<?php
// api/users/manage.php
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

$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID required']);
    exit;
}

// Prevent deleting or downgrading the last admin
function count_admins($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM Users WHERE role = 'ADMIN'");
    $row = $stmt->fetch();
    return intval($row['c'] ?? 0);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $fields = [];
    $params = [':id' => $id];

    if (isset($input['username'])) {
        $fields[] = 'username = :username';
        $params[':username'] = trim($input['username']);
    }

    if (isset($input['role'])) {
        $role = strtoupper(trim($input['role']));
        if (!in_array($role, $ALLOWED_ROLES, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Rol inválido']);
            exit;
        }
        if ($role !== 'ADMIN' && $id == $user['id'] && count_admins($pdo) <= 1) {
            http_response_code(400);
            echo json_encode(['error' => 'No puedes quitar tu propio rol ADMIN si eres el último admin']);
            exit;
        }
        $fields[] = 'role = :role';
        $params[':role'] = $role;
    }

    if (!empty($input['password'])) {
        $fields[] = 'password = :password';
        $params[':password'] = password_hash($input['password'], PASSWORD_BCRYPT);
    }

    if (empty($fields)) {
        echo json_encode(['success' => true]);
        exit;
    }

    $sql = 'UPDATE Users SET ' . implode(', ', $fields) . ', updatedAt = NOW() WHERE id = :id';
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true]);
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

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if ($id == $user['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'No puedes eliminar tu propio usuario']);
        exit;
    }

    // avoid deleting last admin
    $stmt = $pdo->prepare("SELECT role FROM Users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $target = $stmt->fetch();
    if (!$target) {
        http_response_code(404);
        echo json_encode(['error' => 'Usuario no encontrado']);
        exit;
    }
    if ($target['role'] === 'ADMIN' && count_admins($pdo) <= 1) {
        http_response_code(400);
        echo json_encode(['error' => 'Debe existir al menos un ADMIN']);
        exit;
    }

    try {
        $stmt = $pdo->prepare('DELETE FROM Users WHERE id = :id');
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
