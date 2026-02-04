<?php
// api/reports/products-by-date.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');
if (!$SECRET_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'Server misconfiguration: JWT_SECRET missing']);
    exit;
}

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);
if (!$user) {
    if (defined('DEV_MODE') && DEV_MODE === true) {
        $user = ['id' => 1, 'username' => 'System (Dev)', 'role' => 'ADMIN'];
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$date = $_GET['date'] ?? null;
if (!$date) {
    http_response_code(400);
    echo json_encode(['error' => 'date requerido (YYYY-MM-DD)']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT items FROM Bookings WHERE date = :date AND status != 'CANCELLED'");
    $stmt->execute([':date' => $date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totals = [];
    foreach ($rows as $row) {
        if (empty($row['items'])) continue;
        $items = json_decode($row['items'], true);
        if (!is_array($items)) continue;
        foreach ($items as $item) {
            $code = strtoupper(trim($item['code'] ?? ''));
            if ($code === '') continue;
            $kg = floatval($item['kg'] ?? 0);
            if (!isset($totals[$code])) {
                $totals[$code] = 0;
            }
            $totals[$code] += $kg;
        }
    }

    $result = [];
    foreach ($totals as $code => $kg) {
        $result[] = ['code' => $code, 'kg' => round($kg, 2)];
    }
    usort($result, function ($a, $b) {
        return strcmp($a['code'], $b['code']);
    });

    echo json_encode([
        'date' => $date,
        'totalItems' => count($result),
        'products' => $result
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
