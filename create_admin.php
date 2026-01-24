<?php
// create_admin.php
// Script de utilidad para crear o actualizar el usuario administrador sin necesidad de acceso SQL directo.
// SUBIR AL SERVIDOR, EJECUTAR UNA VEZ Y BORRAR.

require_once 'api/db.php';

// CONFIGURACIÓN DEL NUEVO ADMIN
$NEW_USER = 'admin';
$NEW_PASS = 'admin123'; // CAMBIAR ESTO POR UNA CONTRASEÑA SEGURA
$ROLE = 'ADMIN';

echo "<h1>Gestión de Usuario Admin</h1>";

try {
    // 1. Verificar si existe
    $stmt = $pdo->prepare("SELECT id FROM Users WHERE username = :u");
    $stmt->execute([':u' => $NEW_USER]);
    $exists = $stmt->fetch();

    $hash = password_hash($NEW_PASS, PASSWORD_DEFAULT);

    if ($exists) {
        // Actualizar
        $stmt = $pdo->prepare("UPDATE Users SET password = :p, role = :r WHERE username = :u");
        $stmt->execute([':p' => $hash, ':r' => $ROLE, ':u' => $NEW_USER]);
        echo "<p style='color:green'>Usuario <strong>$NEW_USER</strong> actualizado exitosamente.</p>";
    } else {
        // Crear
        $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES (:u, :p, :r)");
        $stmt->execute([':u' => $NEW_USER, ':p' => $hash, ':r' => $ROLE]);
        echo "<p style='color:green'>Usuario <strong>$NEW_USER</strong> creado exitosamente.</p>";
    }

    echo "<p>Recuerda borrar este archivo después de usarlo.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}
?>
