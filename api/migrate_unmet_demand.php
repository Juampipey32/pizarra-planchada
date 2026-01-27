<?php
/**
 * Migration: Create UnmetDemand table and LogisticDeviations table
 *
 * Tracks:
 * - Products removed or quantity reduced from orders
 * - Cancelled orders
 * - Logistic deviations (delays, changes)
 */

require_once 'db.php';

try {
    echo "<h2>Creando tablas de Demanda Insatisfecha y Desvíos Logísticos...</h2>";

    // 1. Create UnmetDemand table
    echo "<h3>1. Creando tabla UnmetDemand...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS UnmetDemand (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            client VARCHAR(255) NOT NULL,
            clientCode VARCHAR(50),
            orderNumber VARCHAR(50),
            product_code VARCHAR(50) NOT NULL,
            product_name VARCHAR(255),
            original_qty DECIMAL(10,2) NOT NULL,
            final_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
            unmet_qty DECIMAL(10,2) NOT NULL,
            original_kg DECIMAL(10,3) NOT NULL,
            final_kg DECIMAL(10,3) NOT NULL DEFAULT 0,
            unmet_kg DECIMAL(10,3) NOT NULL,
            reason ENUM('reduced', 'cancelled', 'deleted_item', 'out_of_stock', 'client_request', 'other') NOT NULL,
            reason_detail TEXT,
            date DATE NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_booking_id (booking_id),
            INDEX idx_client (client),
            INDEX idx_product_code (product_code),
            INDEX idx_date (date),
            INDEX idx_reason (reason),

            FOREIGN KEY (booking_id) REFERENCES Bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES Users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Registro de demanda insatisfecha: productos eliminados o reducidos'
    ");
    echo "<p>✓ Tabla UnmetDemand creada.</p>";

    // 2. Create LogisticDeviations table
    echo "<h3>2. Creando tabla LogisticDeviations...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS LogisticDeviations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id INT NOT NULL,
            deviation_type ENUM('delay', 'early', 'door_change', 'duration_change', 'cancellation') NOT NULL,
            planned_start_time TIME,
            real_start_time TIME,
            planned_end_time TIME,
            real_end_time TIME,
            planned_duration INT,
            real_duration INT,
            deviation_minutes INT,
            planned_door VARCHAR(50),
            real_door VARCHAR(50),
            reason TEXT,
            impact_level ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            date DATE NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_booking_id (booking_id),
            INDEX idx_deviation_type (deviation_type),
            INDEX idx_date (date),
            INDEX idx_impact (impact_level),

            FOREIGN KEY (booking_id) REFERENCES Bookings(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES Users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Registro de desvíos logísticos: retrasos, adelantos, cambios'
    ");
    echo "<p>✓ Tabla LogisticDeviations creada.</p>";

    // 3. Add cancellation_reason field to Bookings if not exists
    echo "<h3>3. Agregando campo cancellation_reason a Bookings...</h3>";
    try {
        $pdo->exec("
            ALTER TABLE Bookings
            ADD COLUMN IF NOT EXISTS cancellation_reason TEXT
            COMMENT 'Razón de cancelación del pedido'
        ");
        echo "<p>✓ Campo cancellation_reason agregado.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p>⚠ Campo cancellation_reason ya existe.</p>";
        } else {
            throw $e;
        }
    }

    // 4. Add original_items field to track changes
    echo "<h3>4. Agregando campo original_items a Bookings...</h3>";
    try {
        $pdo->exec("
            ALTER TABLE Bookings
            ADD COLUMN IF NOT EXISTS original_items JSON DEFAULT NULL
            COMMENT 'Items originales antes de edición (para comparar)'
        ");
        echo "<p>✓ Campo original_items agregado.</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "<p>⚠ Campo original_items ya existe.</p>";
        } else {
            throw $e;
        }
    }

    echo "<h3>✅ Migración completada exitosamente.</h3>";
    echo "<h4>Próximos pasos:</h4>";
    echo "<ul>";
    echo "<li>Modificar manage.php para detectar cambios en items</li>";
    echo "<li>Crear endpoints de consulta de demanda insatisfecha</li>";
    echo "<li>Crear dashboard de análisis</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h2>❌ Error en la migración:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<p>Código de error: " . $e->getCode() . "</p>";
}
?>
