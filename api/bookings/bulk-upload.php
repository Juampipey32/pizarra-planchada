<?php
// api/bookings/bulk-upload.php
// Bulk order upload from Excel files
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: 'secret_key_change_me');

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
    echo json_encode(['error' => 'Archivo Excel requerido']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se pudo recibir el archivo']);
    exit;
}

// Validate Excel file
$mime = @mime_content_type($file['tmp_name']);
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx' && $ext !== 'xls') {
    http_response_code(400);
    echo json_encode(['error' => 'Solo se permiten archivos Excel (.xlsx, .xls)']);
    exit;
}

// Load product coefficients
$productMap = [];
try {
    $stmt = $pdo->query("SELECT code, coefficient FROM Products");
    foreach ($stmt->fetchAll() as $row) {
        $productMap[$row['code']] = (float)$row['coefficient'];
    }
} catch (PDOException $e) {
    // Continue with empty map
}

/**
 * Parse Excel file and extract orders
 * Expected format: Cliente-XXXX.xlsx from the suite
 */
function parseExcelFile($filePath, $productMap) {
    $orders = [];

    try {
        // Read Excel file using ZIP + XML (native PHP)
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('No se pudo abrir el archivo Excel');
        }

        // Read shared strings
        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $xml = $zip->getFromName('xl/sharedStrings.xml');
            $sst = simplexml_load_string($xml);
            foreach ($sst->si as $string) {
                $sharedStrings[] = (string)$string->t;
            }
        }

        // Read first sheet
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (!$sheet) {
            throw new Exception('No se pudo leer la hoja del Excel');
        }

        $xml = simplexml_load_string($sheet);
        $rows = [];

        // Parse rows
        foreach ($xml->sheetData->row as $row) {
            $rowData = [];
            $rowIndex = (int)$row['r'];

            foreach ($row->c as $cell) {
                $cellRef = (string)$cell['r'];
                preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
                $col = $matches[1];

                $value = '';
                if (isset($cell->v)) {
                    $value = (string)$cell->v;
                    // Check if it's a shared string
                    if (isset($cell['t']) && (string)$cell['t'] === 's') {
                        $value = $sharedStrings[(int)$value] ?? '';
                    }
                }

                $rowData[$col] = $value;
            }

            $rows[$rowIndex] = $rowData;
        }

        // Parse orders from rows
        // Expected format based on Cliente-5721.xlsx structure
        $currentOrder = null;
        $headerFound = false;

        foreach ($rows as $rowIndex => $rowData) {
            // Skip empty rows
            if (empty(array_filter($rowData))) {
                continue;
            }

            // Look for order header: "PEDIDO WEB N°" or "Cliente:"
            $firstCell = $rowData['A'] ?? '';
            $secondCell = $rowData['B'] ?? '';
            $thirdCell = $rowData['C'] ?? '';

            // Detect order number
            if (stripos($firstCell, 'PEDIDO') !== false || stripos($secondCell, 'PEDIDO') !== false) {
                // Save previous order if exists
                if ($currentOrder && $currentOrder['kg'] > 0) {
                    $orders[] = $currentOrder;
                }

                // Extract order number
                $orderText = $firstCell . ' ' . $secondCell . ' ' . $thirdCell;
                if (preg_match('/N°\s*([0-9\.]+)/i', $orderText, $m)) {
                    $orderNumber = $m[1];
                } else {
                    $orderNumber = 'N/A';
                }

                $currentOrder = [
                    'orderNumber' => $orderNumber,
                    'client' => '',
                    'clientCode' => null,
                    'kg' => 0,
                    'items' => [],
                    'description' => ''
                ];
            }

            // Detect client info: "Cliente: C#### NAME"
            if (stripos($firstCell, 'Cliente:') !== false || stripos($firstCell, 'Cliente') !== false) {
                $clientText = implode(' ', $rowData);
                if (preg_match('/Cliente:?\s*C(\d{3,})\s+(.+?)\s+CUIT/i', $clientText, $m)) {
                    if ($currentOrder) {
                        $currentOrder['clientCode'] = 'C' . $m[1];
                        $currentOrder['client'] = trim($m[2]);
                    }
                } elseif (preg_match('/C(\d{3,})\s+(.+)/i', $clientText, $m)) {
                    if ($currentOrder) {
                        $currentOrder['clientCode'] = 'C' . $m[1];
                        $currentOrder['client'] = trim($m[2]);
                    }
                }
            }

            // Detect product lines: code (4 digits + optional letter) followed by quantity
            if ($currentOrder && preg_match('/^(\d{4}[A-Z]?)$/', $firstCell, $m)) {
                $code = $m[1];
                $qty = (float)($rowData['B'] ?? 0);

                if ($qty > 0) {
                    $coef = 1.0;
                    if (isset($productMap[$code])) {
                        $coef = (float)$productMap[$code];
                    } else {
                        // Try without letter suffix
                        $numericCode = preg_replace('/[^0-9]/', '', $code);
                        if ($numericCode && isset($productMap[$numericCode])) {
                            $coef = (float)$productMap[$numericCode];
                        }
                    }

                    $kg = $qty * $coef;
                    $currentOrder['kg'] += $kg;
                    $currentOrder['items'][] = [
                        'code' => $code,
                        'qty' => $qty,
                        'coef' => $coef,
                        'kg' => round($kg, 2)
                    ];
                }
            }
        }

        // Add last order
        if ($currentOrder && $currentOrder['kg'] > 0) {
            $orders[] = $currentOrder;
        }

        // Build descriptions
        foreach ($orders as &$order) {
            if (!empty($order['items'])) {
                $topItems = array_slice($order['items'], 0, 3);
                $descriptions = array_map(function($item) {
                    return $item['code'] . ' (' . $item['qty'] . ')';
                }, $topItems);
                $order['description'] = implode(', ', $descriptions);
                if (count($order['items']) > 3) {
                    $order['description'] .= '... +' . (count($order['items']) - 3) . ' más';
                }
            }
            $order['kg'] = round($order['kg'], 2);

            // Suggest duration
            $order['suggestedDuration'] = suggestDurationMinutes($order['kg']);
        }

    } catch (Exception $e) {
        error_log("Excel parse error: " . $e->getMessage());
        throw $e;
    }

    return $orders;
}

function suggestDurationMinutes($kgTotal) {
    if (!$kgTotal || $kgTotal <= 0) return 30;
    $hours = $kgTotal / 2000;
    $minutes = ceil(($hours * 60) / 30) * 30;
    return max(30, (int)$minutes);
}

try {
    $orders = parseExcelFile($file['tmp_name'], $productMap);

    if (empty($orders)) {
        http_response_code(400);
        echo json_encode(['error' => 'No se encontraron pedidos en el archivo Excel']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => count($orders)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar Excel: ' . $e->getMessage()]);
}
