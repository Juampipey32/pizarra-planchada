<?php
// api/reset_data.php

// This script clears all transactional data to start fresh.
// It preserves Users and Products.

require_once __DIR__ . '/db.php';

try {
    // Disable foreign key checks to allow truncation
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    echo "Cleaning Bookings... ";
    $pdo->exec("TRUNCATE TABLE Bookings");
    echo "Done.\n";

    echo "Cleaning UnmetDemand... ";
    // Check if table exists first to avoid error if schema is old
    $pdo->exec("TRUNCATE TABLE UnmetDemand");
    echo "Done.\n";

    echo "Cleaning LogisticDeviations... ";
    $pdo->exec("TRUNCATE TABLE LogisticDeviations");
    echo "Done.\n";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "\nSUCCESS: Database transactional data has been wiped. Ready to start from zero.\n";

} catch (Exception $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    // Ensure checks are re-enabled even on error
    try { $pdo->exec("SET FOREIGN_KEY_CHECKS = 1"); } catch(Exception $x) {}
}
