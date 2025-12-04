<?php
// api/db.php

$host = 'localhost';
$dbname = 'u363074645_pizarra_ventas'; // Verificado por el usuario
$username = 'u363074645_pizarra';      // Verificado por el usuario
$password = 'Juampindonga32-';         // Verificado por el usuario

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    // En producción, no mostrar el error completo por seguridad
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
