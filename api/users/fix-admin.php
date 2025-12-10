<?php
// api/users/fix-admin.php
// EJECUTA ESTO UNA VEZ PARA ASEGURARTE DE TENER UN ADMIN
require_once '../db.php';

header('Content-Type: application/json');

$username = 'admin';
$password = 'admin123'; // Contraseña nueva
$role = 'ADMIN';

try {
    // 1. Verificar si existe
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :u");
    $stmt->execute([':u' => $username]);
    $exists = $stmt->fetch();

    $hash = password_hash($password, PASSWORD_DEFAULT);

    if ($exists) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE Users SET password = :p, role = :r WHERE username = :u");
        $stmt->execute([':p' => $hash, ':r' => $role, ':u' => $username]);
        echo json_encode(['message' => "Usuario 'admin' actualizado. Pass: 'admin123', Rol: 'ADMIN'"]);
    } else {
        // Crear
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (:u, :p, :r)");
        $stmt->execute([':u' => $username, ':p' => $hash, ':r' => $role]);
        echo json_encode(['message' => "Usuario 'admin' creado. Pass: 'admin123', Rol: 'ADMIN'"]);
    }

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
