<?php
// api/update_schema.php
require_once 'db.php';

echo "<h1>Actualización de Esquema de Base de Datos</h1>";

try {
    // 1. Add Columns to Bookings
    $columnsToAdd = [
        "ADD COLUMN IF NOT EXISTS sampi_time INT DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS sampi_on TINYINT(1) DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS priority ENUM('Normal', 'Urgente', 'Lista', 'Espera') DEFAULT 'Normal'",
        "ADD COLUMN IF NOT EXISTS observations TEXT",
        "ADD COLUMN IF NOT EXISTS items JSON",
        "MODIFY COLUMN status ENUM('PENDING', 'PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'PENDING'"
    ];

    foreach ($columnsToAdd as $sql) {
        try {
            $pdo->exec("ALTER TABLE Bookings $sql");
            echo "<p>Ejecutado: $sql</p>";
        } catch (PDOException $e) {
            // Ignore duplication errors or handle specifically
            echo "<p style='color:orange'>Nota sobre: $sql (" . $e->getMessage() . ")</p>";
        }
    }

    echo "<h3>Actualización completada.</h3>";

} catch (PDOException $e) {
    echo "<h2>Error Fatal:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
