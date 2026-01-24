<?php
// api/auth/login.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Usuario y contraseña requeridos']);
    exit;
}

try {
    // Buscar usuario (case insensitive para el username)
    $stmt = $pdo->prepare("SELECT * FROM Users WHERE username = :username LIMIT 1");
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar password
    // Nota: Si tus usuarios viejos no usan hash, esto se actualizará solo si implementas migración.
    // Aquí asumimos que password_verify es lo correcto. Si usas texto plano temporalmente, cambia la condición.
    if ($user && password_verify($password, $user['password'])) {
        
        // Generar Payload del Token
        $payload = [
            'sub' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'], // IMPORTANTE: ADMIN, LOGISTICA o PLANCHADA
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24) // 24 horas
        ];

        $SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
        if (!$SECRET_KEY) {
            http_response_code(500);
            echo json_encode(['error' => 'Server misconfiguration: JWT_SECRET missing']);
            exit;
        }
        $token = generate_jwt($payload, $SECRET_KEY);

        echo json_encode([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'] // Devolvemos el rol explícito
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales inválidas']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de base de datos']);
}
?>
