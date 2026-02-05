<?php
/**
 * api/deviations/index.php
 * Query logistic deviations with filters and aggregated KPIs/rankings
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

// Filters
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$client = $_GET['client'] ?? null;

try {
    $sql = "SELECT
            d.*,
            b.client,
            b.clientCode,
            b.orderNumber,
            b.resourceId,
            b.date AS booking_date
        FROM LogisticDeviations d
        LEFT JOIN Bookings b ON d.booking_id = b.id
        WHERE d.date BETWEEN :start_date AND :end_date";

    $params = [
        ':start_date' => $startDate,
        ':end_date' => $endDate
    ];

    if ($client) {
        $sql .= " AND b.client LIKE :client";
        $params[':client'] = "%$client%";
    }

    $sql .= " ORDER BY d.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRecords = count($records);
    $sumDeviation = 0;
    $sumAbsDeviation = 0;
    $totalDelayMinutes = 0;
    $totalEarlyMinutes = 0;
    $byType = [];
    $byClient = [];

    foreach ($records as $record) {
        $minutes = isset($record['deviation_minutes']) ? (int)$record['deviation_minutes'] : 0;
        $sumDeviation += $minutes;
        $sumAbsDeviation += abs($minutes);
        if ($minutes > 0) {
            $totalDelayMinutes += $minutes;
        } elseif ($minutes < 0) {
            $totalEarlyMinutes += abs($minutes);
        }

        $type = $record['deviation_type'] ?? 'unknown';
        if (!isset($byType[$type])) {
            $byType[$type] = 0;
        }
        $byType[$type]++;

        $clientName = $record['client'] ?: 'Sin cliente';
        if (!isset($byClient[$clientName])) {
            $byClient[$clientName] = [
                'count' => 0,
                'sum_deviation' => 0,
                'sum_abs' => 0,
                'max_delay' => 0,
                'max_early' => 0
            ];
        }

        $byClient[$clientName]['count']++;
        $byClient[$clientName]['sum_deviation'] += $minutes;
        $byClient[$clientName]['sum_abs'] += abs($minutes);
        if ($minutes > 0) {
            $byClient[$clientName]['max_delay'] = max($byClient[$clientName]['max_delay'], $minutes);
        }
        if ($minutes < 0) {
            $byClient[$clientName]['max_early'] = min($byClient[$clientName]['max_early'], $minutes);
        }
    }

    $clientsRanking = [];
    foreach ($byClient as $clientName => $data) {
        $count = max(1, (int)$data['count']);
        $clientsRanking[$clientName] = [
            'client' => $clientName,
            'count' => $data['count'],
            'avg_deviation' => round($data['sum_deviation'] / $count, 2),
            'avg_abs_deviation' => round($data['sum_abs'] / $count, 2),
            'max_delay' => $data['max_delay'],
            'max_early' => $data['max_early']
        ];
    }

    // Worst: highest avg_abs_deviation
    $worstClients = $clientsRanking;
    usort($worstClients, function($a, $b) {
        return $b['avg_abs_deviation'] <=> $a['avg_abs_deviation'];
    });

    // Best: lowest avg_abs_deviation
    $bestClients = $clientsRanking;
    usort($bestClients, function($a, $b) {
        return $a['avg_abs_deviation'] <=> $b['avg_abs_deviation'];
    });

    $avgDeviation = $totalRecords > 0 ? round($sumDeviation / $totalRecords, 2) : 0;
    $avgAbsDeviation = $totalRecords > 0 ? round($sumAbsDeviation / $totalRecords, 2) : 0;

    echo json_encode([
        'success' => true,
        'records' => $records,
        'stats' => [
            'total_records' => $totalRecords,
            'avg_deviation_minutes' => $avgDeviation,
            'avg_abs_deviation_minutes' => $avgAbsDeviation,
            'total_delay_minutes' => $totalDelayMinutes,
            'total_early_minutes' => $totalEarlyMinutes,
            'by_type' => $byType,
            'worst_clients' => array_slice($worstClients, 0, 10),
            'best_clients' => array_slice($bestClients, 0, 10)
        ],
        'filters' => [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'client' => $client
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
