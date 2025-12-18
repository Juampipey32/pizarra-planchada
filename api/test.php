<?php
// api/test.php - Archivo de diagnóstico

header("Content-Type: application/json");

$diagnostics = [
    "php_version" => phpversion(),
    "curl_available" => function_exists('curl_init'),
    "curl_version" => function_exists('curl_version') ? curl_version()['version'] : 'N/A',
    "allow_url_fopen" => ini_get('allow_url_fopen'),
    "ssl_available" => extension_loaded('openssl'),
    "timestamp" => date('Y-m-d H:i:s')
];

// Test connection to n8n
$testUrl = 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/DATOS_FRONT';

if (function_exists('curl_init')) {
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    
    $diagnostics["n8n_test"] = [
        "url" => $testUrl,
        "http_code" => $httpCode,
        "curl_error" => $curlError ?: null,
        "curl_errno" => $curlErrno,
        "response_length" => strlen($response),
        "response_preview" => substr($response, 0, 200)
    ];
} else {
    $diagnostics["n8n_test"] = "cURL not available";
}

echo json_encode($diagnostics, JSON_PRETTY_PRINT);
?>
