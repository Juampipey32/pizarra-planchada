<?php
// api/bookings/bulk_parse.php
// Parse Excel files with multiple pedidos and return suggested kg/duración
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

// Ensure schema for Clients table
ensureClientsSchema($pdo);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (in_array($user['role'], ['VISUALIZADOR', 'INVITADO'])) {
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

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($ext !== 'xlsx') {
    http_response_code(400);
    echo json_encode(['error' => 'Solo se permite Excel (.xlsx)']);
    exit;
}

$tmpPath = $file['tmp_name'];
if (!is_uploaded_file($tmpPath)) {
    http_response_code(400);
    echo json_encode(['error' => 'Archivo inválido']);
    exit;
}

// Load product coefficients from DB (tolerant)
$productMap = [];
try {
    $stmt = $pdo->query("SELECT code, coefficient FROM Products");
    foreach ($stmt->fetchAll() as $row) {
        $productMap[$row['code']] = (float)$row['coefficient'];
    }
} catch (PDOException $e) {
    // Ignore if Products table not present
}

function excelSerialToDate($serial) {
    if (!$serial || !is_numeric($serial)) return null;
    $base = new DateTime('1899-12-30');
    $base->modify("+{$serial} days");
    return $base->format('Y-m-d');
}

function parseXlsxRows($path) {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('No se pudo abrir el Excel');
    }

    $sharedStrings = [];
    if (($index = $zip->locateName('xl/sharedStrings.xml')) !== false) {
        $shared = simplexml_load_string($zip->getFromIndex($index));
        foreach ($shared->si as $si) {
            $text = '';
            foreach ($si->t as $t) {
                $text .= (string)$t;
            }
            $sharedStrings[] = $text;
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();

    if (!$sheetXml) {
        throw new Exception('Hoja de cálculo vacía');
    }

    $sheet = simplexml_load_string($sheetXml);
    $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $rows = [];
    foreach ($sheet->xpath('//a:sheetData/a:row') as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $coord = (string)$c['r'];
            $col = preg_replace('/[0-9]/', '', $coord);
            $type = (string)$c['t'];
            $value = (string)$c->v;
            if ($type === 's' && $value !== '') {
                $value = $sharedStrings[(int)$value] ?? $value;
            }
            $cells[$col] = $value;
        }
        $rows[] = $cells;
    }
    return $rows;
}

function detectHeaderRow($rows) {
    foreach ($rows as $idx => $row) {
        if (in_array('N° Pedido', $row) && in_array('Detalle de lo solicitado', $row)) {
            $headers = [];
            foreach ($row as $col => $value) {
                $headers[$col] = trim($value);
            }
            return [$idx, $headers];
        }
    }
    return [null, []];
}

function parseDetailString($detail, $productMap) {
    $items = [];
    $totalKg = 0;

    if (!$detail) return [$items, $totalKg];

    $pattern = '/\*\*\s*([0-9]{3,4}[A-Z]?)\s*\(([0-9.,]+)\)/';
    if (preg_match_all($pattern, $detail, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $code = strtoupper(trim($match[1]));
            $qtyStr = str_replace(['.', ','], ['', '.'], $match[2]);
            $qty = (float)$qtyStr;
            if ($qty <= 0) continue;

            $coef = 1.0;
            if (isset($productMap[$code])) {
                $coef = (float)$productMap[$code];
            } else {
                $numericCode = preg_replace('/[^0-9]/', '', $code);
                if ($numericCode && isset($productMap[$numericCode])) {
                    $coef = (float)$productMap[$numericCode];
                }
            }

            $kg = round($qty * $coef, 2);
            $items[] = [
                'code' => $code,
                'qty' => $qty,
                'coef' => $coef,
                'kg' => $kg
            ];
            $totalKg += $kg;
        }
    }

    $totalKg = round($totalKg, 2);
    return [$items, $totalKg];
}

function buildDescriptionFromItems($items) {
    if (empty($items)) return '';
    $top = array_slice($items, 0, 3);
    $parts = array_map(function ($item) {
        return $item['code'] . ' (' . $item['qty'] . ')';
    }, $top);

    $desc = implode(', ', $parts);
    if (count($items) > 3) {
        $desc .= '... +' . (count($items) - 3) . ' más';
    }
    return $desc;
}

function detectSampiInfo($items, $totalKg) {
    $SAMPI_CODES = ['1011', '1015', '1016'];
    $SAMPI_THRESHOLD = 648; // kg

    $sampiKg = 0;
    $codes = [];

    foreach ($items as $item) {
        $code = strtoupper($item['code'] ?? '');
        $numericCode = preg_replace('/[^0-9]/', '', $code);

        if (in_array($code, $SAMPI_CODES) || in_array($numericCode, $SAMPI_CODES)) {
            $sampiKg += isset($item['kg']) ? (float)$item['kg'] : 0;
            $codes[] = $code ?: $numericCode;
        }
    }

    $sampiKg = round($sampiKg, 2);
    $regularKg = round(max(($totalKg ?: 0) - $sampiKg, 0), 2);
    $needsSampi = $sampiKg > $SAMPI_THRESHOLD;

    return [
        'needsSampi' => $needsSampi,
        'sampiKg' => $sampiKg,
        'regularKg' => $regularKg,
        'sampiCodes' => array_values(array_unique($codes)),
        'sampiDuration' => $needsSampi ? suggestDurationMinutes($sampiKg) : null,
        'regularDuration' => $needsSampi ? suggestDurationMinutes($regularKg) : null
    ];
}

try {
    $rows = parseXlsxRows($tmpPath);
    [$headerIndex, $headers] = detectHeaderRow($rows);

    if ($headerIndex === null) {
        http_response_code(400);
        echo json_encode(['error' => 'No se encontraron columnas de pedidos en el Excel']);
        exit;
    }

    $colMap = array_flip($headers);
    $orders = [];

    for ($i = $headerIndex + 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $orderNumber = $row[$colMap['N° Pedido']] ?? null;
        $detail = $colMap['Detalle de lo solicitado'] ?? null;
        $detailText = $detail ? ($row[$detail] ?? '') : '';

        if (!$orderNumber || $detailText === '') {
            continue;
        }

        [$items, $kg] = parseDetailString($detailText, $productMap);
        $orderDate = isset($colMap['Fecha']) ? excelSerialToDate($row[$colMap['Fecha']] ?? null) : null;

        $clientCode = $row[$colMap['Código Tango Cliente']] ?? null;
        $clientName = $row[$colMap['Cliente']] ?? ('Pedido ' . $orderNumber);

        if ($clientCode) {
            $stmtClient = $pdo->prepare("INSERT INTO Clients (clientCode, clientName)
                VALUES (:code, :name)
                ON DUPLICATE KEY UPDATE clientName = VALUES(clientName)");
            $stmtClient->execute([
                ':code' => $clientCode,
                ':name' => $clientName
            ]);
        }

        $clientBlocked = false;
        $clientBlockedAmount = null;
        if ($clientCode) {
            $stmtClient = $pdo->prepare("SELECT blocked, blocked_amount FROM Clients WHERE clientCode = :code LIMIT 1");
            $stmtClient->execute([':code' => $clientCode]);
            $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
            if ($clientRow && !empty($clientRow['blocked'])) {
                $clientBlocked = true;
                $clientBlockedAmount = $clientRow['blocked_amount'] ?? null;
            }
        }

        $orders[] = [
            'row' => $i + 1,
            'orderNumber' => trim($orderNumber),
            'status' => $row[$colMap['Estado']] ?? null,
            'clientCode' => $clientCode,
            'client' => $clientName,
            'orderDate' => $orderDate,
            'tangoOrder' => $row[$colMap['N° Pedido en Tango']] ?? null,
            'kg' => $kg,
            'duration' => suggestDurationMinutes($kg),
            'description' => buildDescriptionFromItems($items),
            'items' => $items,
            'sampiInfo' => detectSampiInfo($items, $kg),
            'clientBlocked' => $clientBlocked,
            'clientBlockedAmount' => $clientBlockedAmount
        ];
    }

    echo json_encode($orders);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
