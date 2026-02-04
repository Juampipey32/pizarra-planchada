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
        "ADD COLUMN IF NOT EXISTS is_blocked TINYINT(1) DEFAULT 0",
        "ADD COLUMN IF NOT EXISTS blocked_by VARCHAR(50)",
        "ADD COLUMN IF NOT EXISTS blocked_reason TEXT",
        "ADD COLUMN IF NOT EXISTS blocked_debt_amount DECIMAL(10,2)",
        "ADD COLUMN IF NOT EXISTS blocked_at TIMESTAMP NULL",
        "ADD COLUMN IF NOT EXISTS prev_status VARCHAR(20)",
        "ADD COLUMN IF NOT EXISTS prev_resourceId VARCHAR(50)",
        "ADD COLUMN IF NOT EXISTS prev_color VARCHAR(50)",
        "ADD COLUMN IF NOT EXISTS real_start_at DATETIME NULL",
        "MODIFY COLUMN status ENUM('PENDING', 'PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'BLOCKED') DEFAULT 'PENDING'"
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

    // 2. Create Booking Block Audit Table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS BookingBlockAudit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            action ENUM('BLOCK', 'UNBLOCK') NOT NULL,
            blocked_by VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            reason TEXT,
            actor_user_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (booking_id) REFERENCES Bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_user_id) REFERENCES Users(id) ON DELETE SET NULL
        )");
        echo "<p>Tabla 'BookingBlockAudit' verificada/creada.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Nota sobre BookingBlockAudit: " . $e->getMessage() . "</p>";
    }

    // 3. Create Clients Table
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS Clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            clientCode VARCHAR(50) NOT NULL UNIQUE,
            clientName VARCHAR(255),
            blocked TINYINT(1) DEFAULT 0,
            blocked_amount DECIMAL(10,2) DEFAULT NULL,
            blocked_reason TEXT,
            blocked_at TIMESTAMP NULL,
            createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "<p>Tabla 'Clients' verificada/creada.</p>";
    } catch (PDOException $e) {
        echo "<p style='color:orange'>Nota sobre Clients: " . $e->getMessage() . "</p>";
    }

    echo "<h3>Actualización completada.</h3>";

} catch (PDOException $e) {
    echo "<h2>Error Fatal:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
