<?php
// api/bookings/bulk-upload.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

// Auth (Permisivo para que funcione la demo)
$token = get_bearer_token();
$user = verify_jwt($token, defined('JWT_SECRET') ? JWT_SECRET : 'secret_key_change_me');
$userId = $user ? $user['id'] : 1; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió archivo']);
    exit;
}

$fileTmpPath = $_FILES['file']['tmp_name'];

try {
    $handle = fopen($fileTmpPath, "r");
    $createdCount = 0;
    $row = 0;

    $pdo->beginTransaction();

    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
        $row++;
        // Asumimos que la fila 1 son cabeceras si el primer campo no es fecha/numero
        if ($row === 1 && !is_numeric($data[0] ?? '')) continue; 

        // FORMATO ESPERADO CSV:
        // [0] Cliente, [1] Pedido, [2] Kilos, [3] Descripción
        $client = trim($data[0] ?? '');
        if (empty($client)) continue;

        $order = trim($data[1] ?? '');
        $kg = floatval(preg_replace('/[^0-9.]/', '', $data[2] ?? 0)); // Limpiar simbolos
        $desc = trim($data[3] ?? '');

        // Insertar en PENDIENTE
        $stmt = $pdo->prepare("INSERT INTO Bookings (
            client, orderNumber, description, kg, duration, 
            resourceId, date, startTimeHour, startTimeMinute, status, createdBy, createdAt
        ) VALUES (
            :c, :o, :d, :k, 30, 
            'PENDIENTE', CURDATE(), 8, 0, 'PLANNED', :u, NOW()
        )");

        $stmt->execute([
            ':c' => $client,
            ':o' => $order,
            ':d' => $desc . " [Masivo]",
            ':k' => $kg,
            ':u' => $userId
        ]);
        
        $createdCount++;
    }
    
    fclose($handle);
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => "Procesados $createdCount pedidos correctamente."
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Error procesando CSV: ' . $e->getMessage()]);
}
?>