<?php
// api/bookings/n8n-listener.php
// Este archivo recibe los datos crudos de n8n y crea el booking correspondiente.

// Ajusta las rutas según donde guardes este archivo
require_once '../cors.php';
require_once '../db.php';

// Configuración
header('Content-Type: application/json');

// 1. SEGURIDAD SIMPLE (Opcional pero recomendada)
// En n8n, agrega ?secret=mi_clave_secreta a la URL del webhook
$SECRET_TOKEN = 'cambia_esto_por_una_clave_segura';
if (!isset($_GET['secret']) || $_GET['secret'] !== $SECRET_TOKEN) {
    // Si quieres probar rápido sin clave, comenta las siguientes 3 líneas:
    // http_response_code(403);
    // echo json_encode(['error' => 'Acceso denegado. Falta el secret token.']);
    // exit;
}

// 2. RECIBIR DATOS
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

if (!$data || !isset($data['client'])) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido o faltan datos del cliente']);
    exit;
}

// 3. ASEGURAR COLUMNAS EN DB
// (Misma lógica que en tu bulk-create para evitar errores si la DB es vieja)
function ensureColumns($pdo) {
    try {
        $cols = [];
        foreach ($pdo->query("DESCRIBE Bookings") as $row) $cols[] = $row['Field'];
        
        if (!in_array('clientCode', $cols)) $pdo->exec("ALTER TABLE Bookings ADD COLUMN clientCode VARCHAR(50) NULL");
        if (!in_array('orderNumber', $cols)) $pdo->exec("ALTER TABLE Bookings ADD COLUMN orderNumber VARCHAR(50) NULL");
        if (!in_array('kg', $cols)) $pdo->exec("ALTER TABLE Bookings ADD COLUMN kg DECIMAL(10,2) DEFAULT 0");
    } catch (Exception $e) { /* Ignorar si ya existen */ }
}
ensureColumns($pdo);

// 4. PREPARAR DATOS POR DEFECTO
// Como n8n no manda hora ni puerta, los mandamos a una "Bandeja de Entrada"
$resourceId = 'PENDIENTE'; // O usa 'BUFFER', 'NUEVOS', etc.
$startHour = 8; 
$startMinute = 0;
$date = date('Y-m-d'); // Hoy

// Detectar si n8n ya nos avisó del Sampi
$needsSampi = isset($data['sampiInfo']) && $data['sampiInfo']['needsSampi'] === true;

// Función auxiliar para insertar
function createBooking($pdo, $d, $resId, $descSuffix = '') {
    global $date, $startHour, $startMinute;
    
    $stmt = $pdo->prepare("INSERT INTO Bookings (
        client, clientCode, orderNumber, description, kg, duration, 
        resourceId, date, startTimeHour, startTimeMinute, status, color, createdAt
    ) VALUES (
        :client, :code, :order, :desc, :kg, :dur, 
        :resId, :date, :h, :m, 'PLANNED', :color, NOW()
    )");
    
    $stmt->execute([
        ':client' => $d['client'],
        ':code'   => $d['clientCode'] ?? null,
        ':order'  => $d['orderNumber'] ?? null,
        ':desc'   => ($d['description'] ?? '') . $descSuffix,
        ':kg'     => $d['kg'] ?? 0,
        ':dur'    => $d['duration'] ?? 30,
        ':resId'  => $resId,
        ':date'   => $date,
        ':h'      => $startHour,
        ':m'      => $startMinute,
        ':color'  => $resId === 'sampi' ? 'red' : 'blue' // Color diferente para Sampi
    ]);
    
    return $pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();
    $createdIds = [];

    // Lógica de División SAMPI (Espejo de tu lógica en bulk-create)
    // Si n8n dice que necesita Sampi, dividimos el pedido en 2
    if ($needsSampi && isset($data['sampiInfo'])) {
        
        $sampiKg = $data['sampiInfo']['sampiKg'];
        $regularKg = $data['kg'] - $sampiKg; // El total original menos lo de Sampi

        // 1. Pedido Regular (Puerta Pendiente)
        // Recalculamos duración aprox para la parte regular (regla de 3 simple o tu formula)
        $regularDuration = max(30, ceil(($regularKg / 2000) * 60 / 30) * 30);
        
        $regData = $data;
        $regData['kg'] = $regularKg;
        $regData['duration'] = $regularDuration;
        
        $createdIds[] = createBooking($pdo, $regData, $resourceId, ' [REGULAR]');

        // 2. Pedido Sampi (Recurso 'sampi')
        $sampiDuration = max(30, ceil(($sampiKg / 2000) * 60 / 30) * 30);
        
        $sampiData = $data;
        $sampiData['kg'] = $sampiKg;
        $sampiData['duration'] = $sampiDuration;
        
        $createdIds[] = createBooking($pdo, $sampiData, 'sampi', ' [SOLO SAMPI]');

    } else {
        // Pedido Normal (Sin división)
        $createdIds[] = createBooking($pdo, $data, $resourceId);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'ids' => $createdIds, 
        'message' => 'Pedido recibido y agendado en PENDIENTE'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error DB: ' . $e->getMessage()]);
}
?>
