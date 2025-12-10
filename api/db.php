<?php
// api/db.php

// Allow overriding credentials via environment variables or api/config.php
$configPath = __DIR__ . '/config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

$defaults = [
    'host' => 'localhost',
    'dbname' => 'u363074645_pizarra_ventas',
    'username' => 'u363074645_pizarra',
    'password' => 'Juampindonga32-',
];

$host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : $defaults['host']);
$dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : $defaults['dbname']);
$username = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : $defaults['username']);
$password = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : $defaults['password']);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}
?>
