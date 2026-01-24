<?php
// api/n8n-proxy.php
// Proxy para conectar con n8n sin problemas de CORS

error_reporting(E_ALL);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Cargar configuración
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

// URLs de n8n desde config.php o defaults
$WEBHOOKS = [
    'read' => defined('WEBHOOK_READ_DATA') ? WEBHOOK_READ_DATA : 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/DATOS_FRONT',
    'load' => defined('WEBHOOK_LOAD_MASSIVE') ? WEBHOOK_LOAD_MASSIVE : 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/carga_masiva',
    'upload_pdf' => defined('WEBHOOK_PDF_UPLOAD') ? WEBHOOK_PDF_UPLOAD : 'https://juampi-agente-n8n.h1yobv.easypanel.host/webhook/PEDIDOS-COSALTA'
];

$action = $_GET['action'] ?? '';

if (!array_key_exists($action, $WEBHOOKS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Acción inválida', 'valid_actions' => array_keys($WEBHOOKS)]);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL no está disponible en este servidor']);
    exit;
}

$targetUrl = $WEBHOOKS[$action];

// GET Request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión', 'mensaje' => $curlError]);
        exit;
    }
    
    if ($httpCode === 404) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Flujo n8n no encontrado',
            'mensaje' => 'El flujo DATOS_FRONT no está activo en n8n.',
            'url' => $targetUrl
        ]);
        exit;
    }
    
    $decoded = json_decode($response);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(502);
        echo json_encode([
            'error' => 'Respuesta inválida de n8n',
            'http_code' => $httpCode,
            'preview' => substr($response, 0, 200)
        ]);
        exit;
    }
    
    http_response_code($httpCode);
    echo $response;
    exit;
}

// POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if (!empty($_FILES) && isset($_FILES['file'])) {
        $cfile = new CURLFile(
            $_FILES['file']['tmp_name'],
            $_FILES['file']['type'] ?: 'application/octet-stream',
            $_FILES['file']['name']
        );
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);
    } else {
        $rawBody = file_get_contents('php://input');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawBody);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        http_response_code(500);
        echo json_encode(['error' => 'Error de conexión', 'mensaje' => $curlError]);
        exit;
    }
    
    if ($httpCode === 404) {
        http_response_code(503);
        echo json_encode([
            'error' => 'Flujo n8n no encontrado',
            'mensaje' => 'El flujo carga_masiva no está activo en n8n.',
            'url' => $targetUrl
        ]);
        exit;
    }
    
    http_response_code($httpCode);
    echo $response ?: json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Método no permitido']);
?>
