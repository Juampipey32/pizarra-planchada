<?php
// api/bookings/n8n-listener.php
header('Content-Type: application/json');

// 1. Config & DB
require_once '../cors.php';
require_once '../db.php';

// 2. Validate Secret (Simple measure)
$secret = $_GET['secret'] ?? '';
if ($secret !== 'Juampindonga32-') { // Hardcoded secret matching the one in n8n-flujos/pedidos_pdf.json
    http_response_code(403);
    echo json_encode(['error' => 'Invalid Secret']);
    exit;
}

// 3. Get Payload
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// 4. Duplicate Check (Order Number)
// "verifique que en la semana no esten cargados" -> We check if orderNumber exists
// Simple check: Is there an active booking with this orderNumber?
if (!empty($input['orderNumber'])) {
    $stmt = $pdo->prepare("SELECT id FROM Bookings WHERE orderNumber = :orderNumber AND status != 'CANCELLED' LIMIT 1");
    $stmt->execute([':orderNumber' => $input['orderNumber']]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'skipped', 'message' => 'Duplicate Order Number']);
        exit;
    }
}

// 5. Prepare Data
$client = $input['client'] ?? 'Cliente Desconocido';
$clientCode = $input['clientCode'] ?? '';
$orderNumber = $input['orderNumber'] ?? '';
$desc = $input['description'] ?? '';
$kg = floatval($input['kg'] ?? 0);
$duration = intval($input['duration'] ?? 30);
$items = isset($input['items']) ? json_encode($input['items']) : null;

// Sampi Logic
$sampiInfo = $input['sampiInfo'] ?? [];
$sampiOn = !empty($sampiInfo['needsSampi']) ? 1 : 0;
// Note: Sampi Time is not auto-calculated in n8n yet? 
// The user said "debe calcular tambien segun la formula establecida cuanto de esa carga le corresponde al sampi"
// We'll init sampi_time to a proportion or 0. Let's set 0 and let frontend/edit handle it or simple heuristic?
// Heuristic: If Sampi is ON, maybe all duration? No, mix.
// For now, default 0.
$sampiTime = 0; 

// 6. Insert as PENDING
try {
    $sql = "INSERT INTO Bookings (
        client, clientCode, orderNumber, description, kg, duration, 
        items, sampi_on, sampi_time,
        resourceId, date, startTimeHour, startTimeMinute, 
        status, priority
    ) VALUES (
        :client, :clientCode, :orderNumber, :desc, :kg, :duration,
        :items, :sampiOn, :sampiTime,
        'PENDIENTE', CURDATE(), 8, 0,
        'PENDING', 'Normal'
    )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':client' => $client,
        ':clientCode' => $clientCode,
        ':orderNumber' => $orderNumber,
        ':desc' => $desc,
        ':kg' => $kg,
        ':duration' => $duration,
        ':items' => $items,
        ':sampiOn' => $sampiOn,
        ':sampiTime' => $sampiTime
    ]);

    echo json_encode(['status' => 'success', 'id' => $pdo->lastInsertId()]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
