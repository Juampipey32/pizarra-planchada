<?php
// api/bookings/manage.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

// --- CONFIGURACIÓN WEBHOOK SHEET ---
// Use centralized webhook URL
$SHEET_WEBHOOK_URL = defined('SHEET_WEBHOOK_URL') ? SHEET_WEBHOOK_URL : 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/GUARDAR-SHEET'; 

// 1. Auth
$token = get_bearer_token();
// Use centralized secret
$secret = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: 'secret_key_change_me');
$user = verify_jwt($token, $secret);

if (!$user) {
    if (defined('DEV_MODE') && DEV_MODE === true) {
        // Fallback for Dev Mode
        $user = ['id' => 1, 'username' => 'System (Dev)', 'role' => 'ADMIN'];
    } else {
        http_response_code(401); 
        echo json_encode(['error' => 'Unauthorized']); 
        exit;
    }
}

// Fallback removed for security (restored above for DEV_MODE)
//$user = ['id' => 1, 'username' => 'System', 'role' => 'ADMIN'];

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// 2. Permisos (Simplificado para que funcione tu usuario)
// Permitimos a cualquiera que haya pasado el check de token (o el fallback)
// Si quieres ser estricto: if ($user['role'] === 'PLANCHADA' && $method !== 'GET') ...

try {
    if ($method === 'POST') {
        // --- CREAR ---
        $stmt = $pdo->prepare("INSERT INTO Bookings (
            client, clientCode, orderNumber, description, kg, duration, 
            resourceId, date, startTimeHour, startTimeMinute, status, color, createdBy, createdAt
        ) VALUES (
            :client, :clientCode, :orderNumber, :description, :kg, :duration, 
            :resourceId, :date, :h, :m, :status, :color, :createdBy, NOW()
        )");
        
        $data = [
            ':client' => $input['client'],
            ':clientCode' => $input['clientCode'] ?? null,
            ':orderNumber' => $input['orderNumber'] ?? null,
            ':description' => $input['description'] ?? '',
            ':kg' => $input['kg'] ?? 0,
            ':duration' => $input['duration'] ?? 30,
            ':resourceId' => $input['resourceId'],
            ':date' => $input['date'],
            ':h' => $input['startTimeHour'],
            ':m' => $input['startTimeMinute'],
            ':status' => $input['status'] ?? 'PLANNED',
            ':color' => $input['color'] ?? 'blue',
            ':createdBy' => $user['id']
        ];
        
        $stmt->execute($data);
        $newId = $pdo->lastInsertId();
        
        // Disparar Webhook a Sheet (Asíncrono simulado con curl timeout bajo)
        $data['id'] = $newId;
        $data['action'] = 'CREATED';
        sendToWebhook($SHEET_WEBHOOK_URL, $data);
        
        echo json_encode(['success' => true, 'id' => $newId]);

    } elseif ($method === 'PUT') {
        // --- EDITAR ---
        $fields = [];
        $params = [':id' => $input['id']];
        $whitelist = ['client', 'orderNumber', 'description', 'kg', 'duration', 'resourceId', 'date', 'startTimeHour', 'startTimeMinute', 'status', 'color', 'realStartTime', 'realEndTime'];
        
        foreach ($input as $key => $val) {
            if (in_array($key, $whitelist)) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $val;
            }
        }
        
        if (!empty($fields)) {
            $sql = "UPDATE Bookings SET " . implode(', ', $fields) . ", updatedAt = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // Webhook Update
            $input['action'] = 'UPDATED';
            sendToWebhook($SHEET_WEBHOOK_URL, $input);
        }
        
        echo json_encode(['success' => true]);

    } elseif ($method === 'DELETE') {
        // --- ELIMINAR ---
        if (empty($input['id'])) {
            http_response_code(400); echo json_encode(['error' => 'Falta ID']); exit;
        }

        // Primero obtenemos datos para el log/webhook antes de borrar
        $stmtGet = $pdo->prepare("SELECT * FROM Bookings WHERE id = :id");
        $stmtGet->execute([':id' => $input['id']]);
        $deletedData = $stmtGet->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("DELETE FROM Bookings WHERE id = :id");
        $stmt->execute([':id' => $input['id']]);
        
        if ($stmt->rowCount() > 0) {
            if ($deletedData) {
                $deletedData['action'] = 'DELETED';
                $deletedData['status'] = 'ELIMINADO'; // Para que se vea claro en el sheet
                sendToWebhook($SHEET_WEBHOOK_URL, $deletedData);
            }
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'No encontrado']);
        }
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB Error: ' . $e->getMessage()]);
}

// Función auxiliar para enviar datos a n8n sin bloquear la respuesta al usuario
function sendToWebhook($url, $data) {
    if (empty($url)) return;
    
    // Usamos curl con timeout muy bajo (100ms) para no ralentizar la UI
    // No nos importa esperar la respuesta de n8n
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 200); // Fuego y olvido
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>