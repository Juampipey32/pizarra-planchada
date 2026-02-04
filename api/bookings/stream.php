<?php
// api/bookings/stream.php
require_once '../cors.php';
require_once '../db.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

@ini_set('zlib.output_compression', 0);
@ini_set('output_buffering', 'off');
@ini_set('implicit_flush', 1);
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);

$last = isset($_GET['last']) ? $_GET['last'] : null;
$lastTime = $last ? date('Y-m-d H:i:s', strtotime($last)) : null;

echo "event: connected\n";
echo "data: " . json_encode(['connected' => true, 'server_time' => date('c')]) . "\n\n";

// Poll loop for up to 30 seconds
$end = time() + 30;
while (time() < $end) {
    try {
        if ($lastTime) {
            $stmt = $pdo->prepare("SELECT MAX(updatedAt) AS last_update FROM Bookings WHERE updatedAt > :last");
            $stmt->execute([':last' => $lastTime]);
        } else {
            $stmt = $pdo->query("SELECT MAX(updatedAt) AS last_update FROM Bookings");
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdate = $row['last_update'] ?? null;

        if ($lastUpdate) {
            $lastTime = $lastUpdate;
            echo "event: bookings_update\n";
            echo "data: " . json_encode(['last_update' => $lastUpdate]) . "\n\n";
            break;
        }
    } catch (PDOException $e) {
        echo "event: error\n";
        echo "data: " . json_encode(['error' => $e->getMessage()]) . "\n\n";
        break;
    }

    sleep(2);
}

echo "event: heartbeat\n";
echo "data: " . json_encode(['ts' => date('c')]) . "\n\n";
