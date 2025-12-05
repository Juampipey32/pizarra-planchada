<?php
// api/debug_env.php
// Script de diagnóstico para verificar configuración

header('Content-Type: application/json');

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion(),
    'env_webhook_import' => getenv('WEBHOOK_IMPORT') ?: 'NOT SET',
    'curl_available' => function_exists('curl_init'),
    'shell_exec_available' => function_exists('shell_exec'),
    'pdftotext_available' => false,
    'opcache_enabled' => function_exists('opcache_get_status'),
    'opcache_status' => null,
    'upload_php_modified' => null,
    'upload_php_version' => null
];

// Check pdftotext
if (function_exists('shell_exec')) {
    $bin = trim((string) @shell_exec('which pdftotext 2>/dev/null'));
    if ($bin) {
        $diagnostics['pdftotext_available'] = true;
        $diagnostics['pdftotext_path'] = $bin;
    }
}

// Check opcache
if (function_exists('opcache_get_status')) {
    $status = @opcache_get_status();
    if ($status) {
        $diagnostics['opcache_status'] = [
            'enabled' => $status['opcache_enabled'] ?? false,
            'cache_full' => $status['cache_full'] ?? false,
            'restart_pending' => $status['restart_pending'] ?? false
        ];
    }
}

// Check upload.php
$uploadPath = __DIR__ . '/bookings/upload.php';
if (file_exists($uploadPath)) {
    $diagnostics['upload_php_modified'] = date('Y-m-d H:i:s', filemtime($uploadPath));

    // Read first 500 chars to check version
    $content = file_get_contents($uploadPath, false, null, 0, 500);
    if (preg_match('/v(\d+\.\d+)/', $content, $m)) {
        $diagnostics['upload_php_version'] = $m[1];
    }
}

// Test webhook connectivity if configured
$webhookUrl = getenv('WEBHOOK_IMPORT');
if ($webhookUrl) {
    $diagnostics['webhook_test'] = [
        'url' => $webhookUrl,
        'reachable' => false,
        'response_time' => null,
        'error' => null
    ];

    $start = microtime(true);
    $ch = curl_init($webhookUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $diagnostics['webhook_test']['response_time'] = round((microtime(true) - $start) * 1000, 2) . 'ms';
    $diagnostics['webhook_test']['http_code'] = $httpCode;

    if ($error) {
        $diagnostics['webhook_test']['error'] = $error;
    } else {
        $diagnostics['webhook_test']['reachable'] = ($httpCode >= 200 && $httpCode < 500);
    }
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
