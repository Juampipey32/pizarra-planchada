<?php
// api/bookings/upload-pdf.php
header('Content-Type: application/json');

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once '../lib/pdf2text.php'; // Native PDF Library
require_once '../settings.php'; // Keys
require_once __DIR__ . '/helpers.php';
require_once 'sampi_helpers.php'; // New Sampi V2 logic

// Ensure schema for Clients table
ensureClientsSchema($pdo);

// Auth Check
$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. Validate File
if (!isset($_FILES['pdfFile']) || $_FILES['pdfFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

// 3. Extract Text via PHP (Native)
$pdfValues = "";
try {
    $pdf = new PDF2Text();
    $pdfValues = $pdf->decodePDF($_FILES['pdfFile']['tmp_name']);
    
    if (strlen($pdfValues) < 50) {
        throw new Exception("PDF appears empty or unreadable (scanned?).");
    }
} catch (Exception $e) {
    http_response_code(422);
    echo json_encode(['error' => 'Error reading PDF: ' . $e->getMessage()]);
    exit;
}

// 4. Deterministic Parsing (No AI)
$parsedData = parsePDFText($pdfValues);

if (!$parsedData) {
    http_response_code(422);
    echo json_encode(['error' => 'Could not parse PDF structure.']);
    exit;
}

// 5. Process Sampi logic V2 (pallet-based)
$sampiCalc = calculateSampiTime($parsedData['items'], $pdo);
$regularKg = $parsedData['kg'] - $parsedData['sampiKg'];
$regularDuration = $regularKg > 0 ? calculateDuration($regularKg) : 0;

// 6. Save to DB
try {
    $clientBlocked = false;
    $clientBlockedAmount = null;
    if (!empty($parsedData['clientCode'])) {
        $stmtClient = $pdo->prepare("INSERT INTO Clients (clientCode, clientName)
            VALUES (:code, :name)
            ON DUPLICATE KEY UPDATE clientName = VALUES(clientName)");
        $stmtClient->execute([
            ':code' => $parsedData['clientCode'],
            ':name' => $parsedData['client'] ?? null
        ]);

        $stmtClient = $pdo->prepare("SELECT blocked, blocked_amount FROM Clients WHERE clientCode = :code LIMIT 1");
        $stmtClient->execute([':code' => $parsedData['clientCode']]);
        $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
        if ($clientRow && !empty($clientRow['blocked'])) {
            $clientBlocked = true;
            $clientBlockedAmount = $clientRow['blocked_amount'] ?? null;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO Bookings (
        client, clientCode, orderNumber, description, kg, duration,
        items, sampi_on, sampi_time, sampi_pallets,
        resourceId, date, startTimeHour, startTimeMinute,
        status, priority, is_blocked, blocked_by, blocked_reason, blocked_debt_amount, createdBy, createdAt, updatedAt
    ) VALUES (
        :client, :clientCode, :orderNumber, :desc, :kg, :duration,
        :items, :sampiOn, :sampiTime, :sampiPallets,
        'PENDIENTE', CURDATE(), 8, 0,
        :status, 'Normal', :is_blocked, :blocked_by, :blocked_reason, :blocked_debt_amount, :createdBy, NOW(), NOW()
    )");

    $stmt->execute([
        ':client' => $parsedData['client'],
        ':clientCode' => $parsedData['clientCode'],
        ':orderNumber' => $parsedData['orderNumber'],
        ':desc' => "Pedido PDF " . $parsedData['orderNumber'],
        ':kg' => $parsedData['kg'],
        ':duration' => $regularDuration + $sampiCalc['totalMinutes'],
        ':items' => json_encode($parsedData['items']),
        ':sampiOn' => $sampiCalc['hasSampi'] ? 1 : 0,
        ':sampiTime' => $sampiCalc['totalMinutes'],
        ':sampiPallets' => json_encode($sampiCalc['detail']),
        ':createdBy' => $user['id'],
        ':status' => $clientBlocked ? 'BLOCKED' : 'PENDING',
        ':is_blocked' => $clientBlocked ? 1 : 0,
        ':blocked_by' => $clientBlocked ? 'CLIENT' : null,
        ':blocked_reason' => $clientBlocked ? 'Cliente bloqueado' : null,
        ':blocked_debt_amount' => $clientBlocked ? $clientBlockedAmount : null
    ]);

    echo json_encode([
        'success' => true,
        'id' => $pdo->lastInsertId(),
        'data' => array_merge($parsedData, [
            'sampiCalc' => $sampiCalc,
            'totalDuration' => $regularDuration + $sampiCalc['totalMinutes']
        ])
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Error: ' . $e->getMessage()]);
}


// --- PARSING LOGIC ---
function parsePDFText($text) {
    global $pdo;

    // 1. Get coefficients from database
    $stmt = $pdo->query("SELECT code, coefficient FROM Products");
    $weights = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $weights[$row['code']] = floatval($row['coefficient']);
    }

    // Fallback to hardcoded values if DB empty
    if (empty($weights)) {
        $weights = [
            "1003"=> 3.80, "1010"=> 1.00, "1011"=> 1.00, "1013"=> 0.13, "1014"=> 1.00,
            "1015"=> 1.00, "1016"=> 1.00, "1018"=> 1.00, "1019"=> 0.13, "1020"=> 1.00,
            "1021"=> 1.00, "1022"=> 0.40, "1025"=> 1.00, "1026"=> 0.25, "1027"=> 1.00,
            "1028"=> 0.18, "1031"=> 4.00, "1036"=> 0.18, "1040"=> 1.00, "1045"=> 1.00,
            "1050"=> 0.30, "1053"=> 5.00, "1054"=> 10.00, "1055"=> 25.00, "1056"=> 1.00,
            "1059"=> 3.80, "1061"=> 2.00, "1063"=> 4.00, "1066"=> 3.60, "1067"=> 0.50,
            "1068"=> 1.00, "1069"=> 2.60, "1070"=> 0.40, "1071"=> 0.40, "1073"=> 1.30,
            "1074"=> 1.20, "1078"=> 0.30, "1086"=> 1.00, "1088"=> 1.00, "1091"=> 2.00,
            "1097"=> 0.50, "1098"=> 1.00, "1134"=> 0.20, "1139"=> 0.40, "1143"=> 0.20,
            "1144"=> 0.40, "1148"=> 10.00, "1151"=> 5.00, "1827"=> 1.00, "1859"=> 3.80,
            "1863"=> 4.20, "1891"=> 4.00, "1893"=> 4.00, "1894"=> 1.20
        ];
    }

    // Get Sampi codes (V2: 7 codes instead of 3)
    $sampiCodes = ['1011', '1014', '1015', '1016', '1059', '1063', '1066'];

    // 2. Extract Header Info
    $client = "Cliente Desconocido";
    $clientCode = "";
    $orderNumber = "";

    // Order Number: "PEDIDO WEB N° 958.388"
    if (preg_match('/PEDIDO\s+WEB\s+N\S*\s*([\d\.]+)/i', $text, $m)) {
        $orderNumber = str_replace('.', '', $m[1]);
    } else if (preg_match('/N\S*\s*Pedido\s*[:\.]?\s*([\d\.]+)/i', $text, $m)) {
        $orderNumber = str_replace('.', '', $m[1]);
    }

    // Client Code: "C7080"
    if (preg_match('/\b(C\d{4})\b/', $text, $m)) {
        $clientCode = $m[1];
    }

    // Client Name: Look for "Cliente:"
    if (preg_match('/Cliente\s*:\s*(.*?)(?:\n|CUIT|$)/is', $text, $m)) {
        $clean = trim(str_replace('CUIT', '', $m[1]));
        if (strlen($clean) > 3) $client = $clean;
    }

    // 3. Extract Items
    // Search for lines starting with 4 digits.
    // Logic: Code (4) + Text (Desc) + Number (Qty)
    $items = [];
    $totalKg = 0;
    $sampiKg = 0;

    // Split by lines or "noise" (since raw text might be linear)
    // If strict newlines lost, we rely on "Code...Qty" repeating pattern.
    // Try to capture: "1011LECHE... 100" (heuristic)
    
    // Regex: 4 digits, then non-digits (desc), then digits (qty)
    // We iterate all matches
    preg_match_all('/(\b\d{4}\b)([^\d]+?)(\d+)[\s\.]*(?:SACHET|UNIDAD|KG|LT|POTE)/i', $text, $matches, PREG_SET_ORDER);
    
    // Use fallback if units not present
    if (count($matches) < 1) {
         preg_match_all('/(\b\d{4})([^\d\n]+)(\d+)/', $text, $matches, PREG_SET_ORDER);
    }

    foreach ($matches as $m) {
        $code = $m[1];
        $rawDesc = trim($m[2]);
        $qty = intval($m[3]);
        
        // Filter: Code must be in our known list? Or valid range?
        // 1xxx or 2xxx usually.
        if ($code < 1000 || $code > 9999) continue;

        $weight = $weights[$code] ?? 1.0;
        $lineKg = $qty * $weight;
        
        $items[] = [
            'code' => $code,
            'desc' => $rawDesc, // Optional
            'qty' => $qty,
            'coef' => $weight,
            'kg' => $lineKg
        ];

        $totalKg += $lineKg;
        
        if (in_array($code, $sampiCodes)) {
             $sampiKg += $lineKg;
        }
    }

    return [
        'client' => $client,
        'clientCode' => $clientCode,
        'orderNumber' => $orderNumber,
        'items' => $items,
        'kg' => $totalKg,
        'sampiKg' => $sampiKg
    ];
}
?>
?>
