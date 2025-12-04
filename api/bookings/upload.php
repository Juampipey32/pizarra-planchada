<?php
// api/bookings/upload.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';
$WEBHOOK_IMPORT = getenv('WEBHOOK_IMPORT') ?: null;

// Auth
$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
if ($user['role'] === 'VISUALIZADOR') {
    http_response_code(403);
    echo json_encode(['error' => 'Read only access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'PDF requerido']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se pudo recibir el archivo']);
    exit;
}

$mime = @mime_content_type($file['tmp_name']);
if ($mime && stripos($mime, 'pdf') === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Solo se permiten PDF']);
    exit;
}

$tmpPath = $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Archivo invalido']);
    exit;
}

function extractTextFromPdf($path) {
    // Try pdftotext if available and shell_exec is enabled
    if (function_exists('shell_exec')) {
        $bin = trim((string) @shell_exec('which pdftotext 2>/dev/null'));
        if ($bin) {
            $out = tempnam(sys_get_temp_dir(), 'pizarra_txt_');
            @shell_exec(escapeshellarg($bin) . ' -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($out));
            $txt = @file_get_contents($out);
            @unlink($out);
            if ($txt) {
                return $txt;
            }
        }
    }

    // Fallback: read raw bytes and strip non-ascii
    $raw = @file_get_contents($path);
    if ($raw) {
        $clean = preg_replace('/[^\x20-\x7E\r\n\t]/', ' ', $raw);
        return $clean;
    }
    return '';
}

// Load product coefficients into map (tolerant if table missing)
$productMap = [];
try {
    $stmt = $pdo->query("SELECT code, coefficient FROM Products");
    foreach ($stmt->fetchAll() as $row) {
        $productMap[$row['code']] = (float)$row['coefficient'];
    }
} catch (PDOException $e) {
    // If Products table not present yet, continue with empty map (coef default 1)
}

function suggestDurationMinutes($kgTotal) {
    // Base rule: 2000 kg por hora, redondeo a 30m
    if (!$kgTotal || $kgTotal <= 0) return 30;
    $hours = $kgTotal / 2000;
    $minutes = ceil(($hours * 60) / 30) * 30;
    return max(30, (int)$minutes);
}

function parsePdfData($text, $productMap, $fileNameBase) {
    $response = [
        'client' => $fileNameBase,
        'clientCode' => null,
        'orderNumber' => null,
        'description' => '',
        'kg' => 0,
        'duration' => null,
        'items' => []
    ];

    if (!$text) {
        return $response;
    }

    // Extract meta
    if (preg_match('/PEDIDO\\s+WEB\\s+N°\\s*([0-9\\.]+)/i', $text, $m)) {
        $response['orderNumber'] = trim($m[1]);
    }

    // Extract client code and name from line like:
    // Cliente:    C4095 LARRAUX ANCHEZAR GASTON                 CUIT: 20-31194213-2
    if (preg_match('/Cliente:\\s*C(\\d{3,})\\s+(.+?)\\s+CUIT:/i', $text, $m)) {
        $response['clientCode'] = 'C' . $m[1];
        $response['client'] = trim($m[2]);
    } elseif (preg_match('/\\bC(\\d{3,})\\b/', $text, $m)) {
        // Fallback: just get the code
        $response['clientCode'] = 'C' . $m[1];
    }

    // Parse product lines - look for product codes anywhere in the line
    $lines = preg_split('/\\r\\n|\\r|\\n/', $text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;

        // Skip header lines
        if (stripos($line, 'ARTÍCULO') !== false || stripos($line, 'PEDIDO WEB') !== false) {
            continue;
        }

        // Look for product code pattern: 4-digit number optionally followed by letter(s)
        // The code should be followed by whitespace and then a quantity
        if (!preg_match('/\\b(\\d{4}[A-Z]?)\\s+(\\d+)/', $line, $m)) {
            continue;
        }

        $code = $m[1];  // Product code (e.g., 1011, 1018D)
        $qty = (float)$m[2];  // Quantity (first number after code)

        // Get coefficient from product map
        $coef = 1.0;
        if (isset($productMap[$code])) {
            $coef = (float)$productMap[$code];
        } else {
            // Try without the letter suffix
            $numericCode = preg_replace('/[^0-9]/', '', $code);
            if ($numericCode && isset($productMap[$numericCode])) {
                $coef = (float)$productMap[$numericCode];
            }
        }

        $kg = $qty * $coef;
        $response['kg'] += $kg;
        $response['items'][] = [
            'code' => $code,
            'qty' => $qty,
            'coef' => $coef,
            'kg' => round($kg, 2)
        ];
    }

    $response['kg'] = round($response['kg'], 2);
    $response['duration'] = suggestDurationMinutes($response['kg']);

    // Build description from parsed items (top 3 products)
    if (!empty($response['items'])) {
        $topItems = array_slice($response['items'], 0, 3);
        $descriptions = array_map(function($item) {
            return $item['code'] . ' (' . $item['qty'] . ')';
        }, $topItems);
        $response['description'] = implode(', ', $descriptions);
        if (count($response['items']) > 3) {
            $response['description'] .= '... +' . (count($response['items']) - 3) . ' más';
        }
    } elseif (!$response['description']) {
        // Fallback: first non-empty line after meta
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $response['description'] = substr($line, 0, 120);
                break;
            }
        }
    }

    return $response;
}

$text = extractTextFromPdf($tmpPath);
$baseName = pathinfo($file['name'], PATHINFO_FILENAME);
$response = parsePdfData($text, $productMap, $baseName);

// If a webhook is configured, forward the PDF and return its response
if ($WEBHOOK_IMPORT) {
    try {
        $cfile = curl_file_create($tmpPath, $file['type'] ?: 'application/pdf', $file['name']);
        $postData = ['file' => $cfile];

        $ch = curl_init($WEBHOOK_IMPORT);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new Exception($curlErr);
        }

        if ($httpCode >= 200 && $httpCode < 300 && $resp) {
            // Assume webhook returns JSON with the parsed data
            echo $resp;
            exit;
        } else {
            // Fallback to local parsing if webhook responds with error
            error_log("Webhook import responded HTTP $httpCode, using local parse");
        }
    } catch (Exception $ex) {
        error_log("Webhook import error: " . $ex->getMessage());
    }
}

echo json_encode($response);
