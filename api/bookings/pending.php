<?php
/**
 * api/bookings/pending.php
 * Get all pending bookings (status = PENDING or resourceId = PENDIENTE)
 * Replaces n8n webhook READ_DATA
 */

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : getenv('JWT_SECRET');

// Auth (optional for read, but recommended)
$token = get_bearer_token();
$user = null;
if ($token) {
    $user = verify_jwt($token, $SECRET_KEY);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get all pending bookings
    $stmt = $pdo->prepare("
        SELECT
            b.*,
            u.username as created_by_username
        FROM Bookings b
        LEFT JOIN Users u ON b.createdBy = u.id
        WHERE (
            b.status = 'PENDING'
            OR b.resourceId = 'PENDIENTE'
        )
        AND b.status != 'CANCELLED'
        ORDER BY b.createdAt DESC
    ");

    $stmt->execute();
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse items JSON
    foreach ($bookings as &$booking) {
        if (!empty($booking['items'])) {
            $booking['items'] = json_decode($booking['items'], true);
        }
        if (!empty($booking['sampi_pallets'])) {
            $booking['sampi_pallets'] = json_decode($booking['sampi_pallets'], true);
        }
    }

    echo json_encode([
        'success' => true,
        'count' => count($bookings),
        'bookings' => $bookings
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
