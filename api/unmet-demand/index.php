<?php
/**
 * api/unmet-demand/index.php
 * Query unmet demand records with filters
 */

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');

// Auth
$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get filters from query params
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$client = $_GET['client'] ?? null;
$productCode = $_GET['product_code'] ?? null;
$reason = $_GET['reason'] ?? null;
$groupBy = $_GET['group_by'] ?? null; // client, product, reason, date

try {
    // Build query
    $sql = "SELECT
        u.*,
        b.date as booking_date,
        b.status as booking_status
    FROM UnmetDemand u
    LEFT JOIN Bookings b ON u.booking_id = b.id
    WHERE u.date BETWEEN :start_date AND :end_date";

    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($client) {
        $sql .= " AND u.client LIKE :client";
        $params[':client'] = "%$client%";
    }

    if ($productCode) {
        $sql .= " AND u.product_code = :product_code";
        $params[':product_code'] = $productCode;
    }

    if ($reason) {
        $sql .= " AND u.reason = :reason";
        $params[':reason'] = $reason;
    }

    $sql .= " ORDER BY u.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate aggregated statistics
    $totalUnmetQty = 0;
    $totalUnmetKg = 0;
    $byReason = [];
    $byProduct = [];
    $byClient = [];

    foreach ($records as $record) {
        $totalUnmetQty += $record['unmet_qty'];
        $totalUnmetKg += $record['unmet_kg'];

        // Group by reason
        if (!isset($byReason[$record['reason']])) {
            $byReason[$record['reason']] = [
                'count' => 0,
                'unmet_qty' => 0,
                'unmet_kg' => 0
            ];
        }
        $byReason[$record['reason']]['count']++;
        $byReason[$record['reason']]['unmet_qty'] += $record['unmet_qty'];
        $byReason[$record['reason']]['unmet_kg'] += $record['unmet_kg'];

        // Group by product
        if (!isset($byProduct[$record['product_code']])) {
            $byProduct[$record['product_code']] = [
                'product_name' => $record['product_name'],
                'count' => 0,
                'unmet_qty' => 0,
                'unmet_kg' => 0
            ];
        }
        $byProduct[$record['product_code']]['count']++;
        $byProduct[$record['product_code']]['unmet_qty'] += $record['unmet_qty'];
        $byProduct[$record['product_code']]['unmet_kg'] += $record['unmet_kg'];

        // Group by client
        if (!isset($byClient[$record['client']])) {
            $byClient[$record['client']] = [
                'count' => 0,
                'unmet_qty' => 0,
                'unmet_kg' => 0
            ];
        }
        $byClient[$record['client']]['count']++;
        $byClient[$record['client']]['unmet_qty'] += $record['unmet_qty'];
        $byClient[$record['client']]['unmet_kg'] += $record['unmet_kg'];
    }

    // Sort aggregations
    uasort($byProduct, function($a, $b) {
        return $b['unmet_kg'] <=> $a['unmet_kg'];
    });

    uasort($byClient, function($a, $b) {
        return $b['unmet_kg'] <=> $a['unmet_kg'];
    });

    echo json_encode([
        'success' => true,
        'records' => $records,
        'stats' => [
            'total_records' => count($records),
            'total_unmet_qty' => round($totalUnmetQty, 2),
            'total_unmet_kg' => round($totalUnmetKg, 3),
            'by_reason' => $byReason,
            'by_product' => array_slice($byProduct, 0, 20, true), // Top 20
            'by_client' => array_slice($byClient, 0, 20, true) // Top 20
        ],
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'client' => $client,
            'product_code' => $productCode,
            'reason' => $reason
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
