<?php
// api/bookings/bulk-upload.php
// Disable HTML error output, only JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../cors.php';
require_once '../db.php';
require_once '../jwt_helper.php';
require_once __DIR__ . '/helpers.php';

// Try to load Sampi helpers if it exists
if (file_exists(__DIR__ . '/sampi_helpers.php')) {
    require_once __DIR__ . '/sampi_helpers.php';
}

// Try to load PhpSpreadsheet if it exists
if (file_exists(__DIR__ . '/../lib/autoload.php')) {
    require_once __DIR__ . '/../lib/autoload.php';
}

header('Content-Type: application/json');

$SECRET_KEY = defined('JWT_SECRET') ? JWT_SECRET : (getenv('JWT_SECRET') ?: 'secret_key_change_me');

// Check if DEV_MODE is enabled
$devMode = defined('DEV_MODE') && DEV_MODE === true;

if ($devMode) {
    // Dev mode: skip authentication
    $user = ['id' => 1, 'username' => 'dev', 'role' => 'ADMIN'];
} else {
    $token = get_bearer_token();
    $user = verify_jwt($token, $SECRET_KEY);

    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
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
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            throw new Exception('Excel library (PhpSpreadsheet) is missing. Please convert to CSV and upload.');
        }
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
            $items = parseItemsFromBooking($booking, $pdo);

            // Calculate regular KG and Sampi time using new V2 logic
            $totalKg = 0;
            $sampiProducts = [];
            $regularProducts = [];

            foreach ($items as $item) {
                $kg = $item['kg'] ?? ($item['qty'] * ($item['coef'] ?? 1));
                $totalKg += $kg;

                // Check if isSampiProduct function exists
                if (function_exists('isSampiProduct') && isSampiProduct($item['code'] ?? '', $pdo)) {
                    $sampiProducts[] = $item;
                } else {
                    $regularProducts[] = $item;
                }
            }

            // Calculate Sampi time (pallet-based) if function exists
            $sampiCalc = ['totalMinutes' => 0, 'hasSampi' => false, 'detail' => []];
            if (function_exists('calculateSampiTime')) {
                $sampiCalc = calculateSampiTime($items, $pdo);
            }

            // Calculate regular duration (weight-based)
            $regularKg = array_reduce($regularProducts, function($sum, $item) {
                return $sum + ($item['kg'] ?? ($item['qty'] * ($item['coef'] ?? 1)));
            }, 0);

            // Use calculateDuration if it exists, otherwise fallback
            if (function_exists('calculateDuration')) {
                $regularDuration = calculateDuration($regularKg);
            } else {
                // Fallback: 2000 kg per hour, 30 min blocks
                $blocks = max(1, ceil($regularKg / 1000));
                $regularDuration = $blocks * 30;
            }

            // Prepare booking data
            $bookingData = [
                'client' => $booking['cliente'] ?? $booking['client'] ?? 'Sin cliente',
                'orderNumber' => $booking['ordernumber'] ?? $booking['order_number'] ?? $booking['n° pedido'] ?? '',
                'clientCode' => $booking['clientcode'] ?? $booking['client_code'] ?? '',
                'description' => $booking['descripcion'] ?? $booking['description'] ?? '',
                'kg' => $totalKg,
                'duration' => $regularDuration + $sampiCalc['totalMinutes'],
                'sampi_on' => $sampiCalc['hasSampi'] ? 1 : 0,
                'sampi_time' => $sampiCalc['totalMinutes'],
                'sampi_pallets' => json_encode($sampiCalc['detail']),
                'items' => json_encode($items),
                'status' => 'PENDING',
                'createdBy' => $user['id'],
                'createdAt' => date('Y-m-d H:i:s'),
                'updatedAt' => date('Y-m-d H:i:s')
            ];

            // Insert into database
            $stmt = $pdo->prepare("
                INSERT INTO Bookings
                (client, orderNumber, clientCode, description, kg, duration, sampi_on, sampi_time, sampi_pallets, items, status, createdBy, createdAt, updatedAt)
                VALUES
                (:client, :orderNumber, :clientCode, :description, :kg, :duration, :sampi_on, :sampi_time, :sampi_pallets, :items, :status, :createdBy, :createdAt, :updatedAt)
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

function parseItemsFromBooking($booking, $pdo) {
    // Get coefficients from database
    static $coefficients = null;
    if ($coefficients === null) {
        $stmt = $pdo->query("SELECT code, coefficient FROM Products");
        $coefficients = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $coefficients[$row['code']] = floatval($row['coefficient']);
        }
    }

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
                $code = trim($parts[0] ?? '');
                $qty = floatval(trim($parts[1] ?? '1'));
                $coef = isset($parts[2]) ? floatval(trim($parts[2])) : ($coefficients[$code] ?? 1.0);
                $kg = $qty * $coef;

                $items[] = [
                    'code' => $code,
                    'qty' => $qty,
                    'coef' => $coef,
                    'kg' => $kg
                ];
            }
        }
    } elseif (strpos($itemsField, ',') !== false) {
        // Format: code,qty,coef
        $itemParts = explode(',', $itemsField);
        foreach ($itemParts as $itemPart) {
            $parts = explode(';', $itemPart);
            if (count($parts) >= 2) {
                $code = trim($parts[0] ?? '');
                $qty = floatval(trim($parts[1] ?? '1'));
                $coef = isset($parts[2]) ? floatval(trim($parts[2])) : ($coefficients[$code] ?? 1.0);
                $kg = $qty * $coef;

                $items[] = [
                    'code' => $code,
                    'qty' => $qty,
                    'coef' => $coef,
                    'kg' => $kg
                ];
            }
        }
    } else {
        // Single item
        $code = $booking['code'] ?? $booking['codigo'] ?? 'GENERIC';
        $qty = floatval($booking['qty'] ?? $booking['cantidad'] ?? 1);
        $coef = $coefficients[$code] ?? floatval($booking['coef'] ?? $booking['coeficiente'] ?? 1);
        $kg = $qty * $coef;

        $items[] = [
            'code' => $code,
            'qty' => $qty,
            'coef' => $coef,
            'kg' => $kg
        ];
    }

    return $items;
}

// NOTE: calculateSampiAndTime() removed - now using sampi_helpers.php functions:
// - calculateSampiTime($items, $pdo) for pallet-based Sampi calculation
// - calculateDuration($kg) for weight-based duration calculation
