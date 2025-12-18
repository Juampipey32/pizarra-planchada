<?php
// api/bookings/bulk-upload.php
require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../lib/autoload.php'; // PhpSpreadsheet autoloader

header('Content-Type: application/json');

$SECRET_KEY = getenv('JWT_SECRET') ?: 'secret_key_change_me';

$token = get_bearer_token();
$user = verify_jwt($token, $SECRET_KEY);

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['csvFile'];
$tempPath = $file['tmp_name'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

try {
    // Detect file type and parse accordingly
    if ($fileExtension === 'csv') {
        $bookings = parseCSVFile($tempPath);
    } elseif (in_array($fileExtension, ['xlsx', 'xls'])) {
        $bookings = parseExcelFile($tempPath);
    } else {
        throw new Exception('Unsupported file format. Please upload CSV or Excel file.');
    }

    $results = processBulkUpload($bookings, $pdo, $user);

    echo json_encode([
        'success' => true,
        'processed' => $results['processed'],
        'errors' => $results['errors'],
        'message' => "Procesados {$results['processed']} pedidos, {$results['errors']} errores"
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function parseCSVFile($filePath) {
    $bookings = [];
    $handle = fopen($filePath, 'r');

    if (!$handle) {
        throw new Exception('Could not open CSV file');
    }

    $headers = fgetcsv($handle, 1000, ',');
    if (!$headers) {
        throw new Exception('Invalid CSV format');
    }

    while (($row = fgetcsv($handle, 1000, ',')) !== false) {
        if (count($row) < 2) continue; // Skip empty rows

        $booking = [];
        foreach ($headers as $index => $header) {
            $booking[strtolower(trim($header))] = isset($row[$index]) ? trim($row[$index]) : '';
        }

        $bookings[] = $booking;
    }

    fclose($handle);
    return $bookings;
}

function parseExcelFile($filePath) {
    $bookings = [];

    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get headers (first row)
        $headers = [];
        foreach ($sheet->getRowIterator(1) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            foreach ($cellIterator as $cell) {
                $headers[] = strtolower(trim($cell->getValue()));
            }
            break;
        }

        // Read data (from second row)
        foreach ($sheet->getRowIterator(2) as $row) {
            $booking = [];
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $colIndex = 0;
            foreach ($cellIterator as $cell) {
                if (isset($headers[$colIndex])) {
                    $booking[$headers[$colIndex]] = trim($cell->getValue());
                }
                $colIndex++;
            }

            if (count($booking) > 1) {
                $bookings[] = $booking;
            }
        }

    } catch (Exception $e) {
        throw new Exception('Error parsing Excel file: ' . $e->getMessage());
    }

    return $bookings;
}

function processBulkUpload($bookings, $pdo, $user) {
    $processed = 0;
    $errors = 0;
    $results = [];

    foreach ($bookings as $booking) {
        try {
            $items = parseItemsFromBooking($booking);
            $calculation = calculateSampiAndTime($items);

            // Prepare booking data
            $bookingData = [
                'client' => $booking['cliente'] ?? $booking['client'] ?? 'Sin cliente',
                'orderNumber' => $booking['ordernumber'] ?? $booking['order_number'] ?? $booking['n° pedido'] ?? '',
                'clientCode' => $booking['clientcode'] ?? $booking['client_code'] ?? '',
                'description' => $booking['descripcion'] ?? $booking['description'] ?? '',
                'kg' => $calculation['totalKg'],
                'duration' => $calculation['duration'],
                'sampi_on' => $calculation['isSampi'] ? 1 : 0,
                'items' => json_encode($items),
                'status' => 'PENDING',
                'createdBy' => $user['id'],
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO Bookings
                (client, orderNumber, clientCode, description, kg, duration, sampi_on, items, status, createdBy, createdAt, updatedAt)
                VALUES
                (:client, :orderNumber, :clientCode, :description, :kg, :duration, :sampi_on, :items, :status, :createdBy, :createdAt, :updatedAt)
            ");

            $stmt->execute($bookingData);
            $processed++;

        } catch (Exception $e) {
            $errors++;
            $results[] = [
                'booking' => $booking,
                'error' => $e->getMessage()
            ];
        }
    }

    return ['processed' => $processed, 'errors' => $errors, 'details' => $results];
}

function parseItemsFromBooking($booking) {
    $itemsField = $booking['items'] ?? $booking['productos'] ?? '';

    if (!$itemsField) {
        return [];
    }

    $items = [];

    if (strpos($itemsField, ';') !== false) {
        // Format: code;qty;coef
        $itemParts = explode(';', $itemsField);
        foreach ($itemParts as $itemPart) {
            $parts = explode(',', $itemPart);
            if (count($parts) >= 2) {
                $items[] = [
                    'code' => trim($parts[0] ?? ''),
                    'qty' => floatval(trim($parts[1] ?? '1')),
                    'coef' => floatval(trim($parts[2] ?? '1'))
                ];
            }
        }
    } elseif (strpos($itemsField, ',') !== false) {
        // Format: code,qty,coef
        $itemParts = explode(',', $itemsField);
        foreach ($itemParts as $itemPart) {
            $parts = explode(';', $itemPart);
            if (count($parts) >= 2) {
                $items[] = [
                    'code' => trim($parts[0] ?? ''),
                    'qty' => floatval(trim($parts[1] ?? '1')),
                    'coef' => floatval(trim($parts[2] ?? '1'))
                ];
            }
        }
    } else {
        // Single item
        $items[] = [
            'code' => $booking['code'] ?? $booking['codigo'] ?? 'GENERIC',
            'qty' => floatval($booking['qty'] ?? $booking['cantidad'] ?? 1),
            'coef' => floatval($booking['coef'] ?? $booking['coeficiente'] ?? 1)
        ];
    }

    return $items;
}

function calculateSampiAndTime($items) {
    global $SAMPI_CODES, $SAMPI_THRESHOLD_KG, $WEIGHT_PER_HOUR, $SLOT_DURATION;

    $totalKg = 0;
    $sampiKg = 0;

    foreach ($items as $item) {
        $kg = $item['kg'] ?? ($item['qty'] * ($item['coef'] ?? 1));
        $totalKg += $kg;
        if (in_array($item['code'] ?? '', $SAMPI_CODES)) {
            $sampiKg += $kg;
        }
    }

    $rawMinutes = ($totalKg / $WEIGHT_PER_HOUR) * 60;
    $blocks = max(1, ceil($rawMinutes / $SLOT_DURATION));
    $duration = $blocks * $SLOT_DURATION;

    return [
        'totalKg' => $totalKg,
        'sampiKg' => $sampiKg,
        'duration' => $duration,
        'isSampi' => $sampiKg > $SAMPI_THRESHOLD_KG
    ];
}