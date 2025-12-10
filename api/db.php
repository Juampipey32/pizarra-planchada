<?php
// api/db.php

// Load centralized settings
$settingsPath = __DIR__ . '/settings.php';
if (file_exists($settingsPath)) {
    require_once $settingsPath;
}
// Fallback to config.php if it exists (legacy/local override)
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

$host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: $defaults['host']);
$dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_NAME') ?: $defaults['dbname']);
$username = defined('DB_USER') ? DB_USER : (getenv('DB_USER') ?: $defaults['username']);
$password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASS') ?: $defaults['password']);

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
