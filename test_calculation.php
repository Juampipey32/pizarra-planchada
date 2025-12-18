<?php
// Test script to verify Sampi calculation logic

require_once 'api/bookings/helpers.php';
require_once 'public/js/config.js'; // This would need to be parsed, let's define constants directly

// Define constants from config.js
define('SAMPI_CODES', ['1011', '1015', '1016']);
define('SAMPI_THRESHOLD_KG', 648);
define('SLOT_DURATION', 30);
define('KG_PER_BLOCK', 1500);

function testSampiCalculation($items) {
    $totalKg = 0;
    $sampiKg = 0;

    foreach ($items as $item) {
        $kg = $item['kg'] ?? ($item['qty'] * ($item['coef'] ?? 1));
        $totalKg += $kg;
        if (in_array($item['code'] ?? '', SAMPI_CODES)) {
            $sampiKg += $kg;
        }
    }

    // Calculate duration: 30 min por cada 1500 kg (bloques completos)
    $blocks = max(1, ceil($totalKg / KG_PER_BLOCK));
    $duration = $blocks * SLOT_DURATION;

    return [
        'totalKg' => $totalKg,
        'sampiKg' => $sampiKg,
        'duration' => $duration,
        'isSampi' => $sampiKg > SAMPI_THRESHOLD_KG
    ];
}

// Test cases
$testCases = [
    [
        'name' => 'Pedido normal',
        'items' => [
            ['code' => '1003', 'qty' => 1, 'coef' => 4.00],
            ['code' => '1010', 'qty' => 1, 'coef' => 1.00]
        ]
    ],
    [
        'name' => 'Pedido Sampi debajo del umbral',
        'items' => [
            ['code' => '1011', 'qty' => 1, 'coef' => 1.00],
            ['code' => '1015', 'qty' => 1, 'coef' => 1.00]
        ]
    ],
    [
        'name' => 'Pedido Sampi sobre el umbral',
        'items' => [
            ['code' => '1011', 'qty' => 100, 'coef' => 1.00], // 100kg of Sampi product
            ['code' => '1015', 'qty' => 100, 'coef' => 1.00]  // 100kg of Sampi product
        ]
    ],
    [
        'name' => 'Pedido mixto',
        'items' => [
            ['code' => '1011', 'qty' => 50, 'coef' => 1.00],  // 50kg Sampi
            ['code' => '1003', 'qty' => 100, 'coef' => 4.00]  // 400kg normal
        ]
    ]
];

echo "=== Testing Sampi Calculation Logic ===\n\n";

foreach ($testCases as $testCase) {
    $result = testSampiCalculation($testCase['items']);
    $isSampi = $result['isSampi'] ? 'YES' : 'NO';

    echo "Test: {$testCase['name']}\n";
    echo "  Total KG: " . number_format($result['totalKg'], 2) . "\n";
    echo "  Sampi KG: " . number_format($result['sampiKg'], 2) . "\n";
    echo "  Duration: {$result['duration']} min\n";
    echo "  Sampi Active: {$isSampi}\n";
    echo "  Threshold: " . number_format(SAMPI_THRESHOLD_KG, 2) . " kg\n\n";
}

// Now let's check the current logic in dashboard.html
echo "\n=== Checking Current Dashboard Logic ===\n";
echo "The dashboard should:\n";
echo "1. Fetch pending bookings with status=PENDING\n";
echo "2. Calculate totalKg and sampiKg for each booking\n";
echo "3. Calculate duration based on totalKg\n";
echo "4. Remove duplicates based on orderNumber/clientCode\n";
echo "5. Display in pending list with correct calculations\n\n";

echo "Current implementation should ensure:\n";
echo "- Cálculo Sampi usando SAMPI_CODES y THRESHOLD\n";
echo "- Duración basada en bloques de 30 min cada 1500 kg\n";
echo "- Eliminación de duplicados por cliente/pedido\n";
echo "- Render correcto en pendientes/tablero\n";
