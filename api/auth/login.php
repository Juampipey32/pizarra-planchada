<?php
// api/auth/login.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once '../users/bootstrap.php';

// Load .env manually if needed, or use hardcoded secret for now (User should set env var in hosting)
$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';
$ROLES = bootstrap_roles();

// Ensure schema and seed default admin to avoid lockouts if install.php was skipped
ensure_users_schema($pdo, $ROLES);
ensure_admin_exists($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $username = $input['username'] ?? '';
    $password = $input['password'] ?? '';

    if (!$username || !$password) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
        exit;
    }

    // Verify password (bcrypt)
    if (password_verify($password, $user['password'])) {
        $payload = [
            'id' => $user['id'],
            'role' => $user['role'],
            'exp' => time() + (8 * 3600) // 8 hours
        ];
        $token = generate_jwt($payload, $SECRET_KEY);
        echo json_encode(['token' => $token, 'role' => $user['role']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
}
?>
