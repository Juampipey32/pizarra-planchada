<?php
/**
 * Migración del Sistema Sampi V2
 *
 * Cambios:
 * - La lógica cambia de peso (kg) a unidades/pallets
 * - Cada pallet tarda 4 minutos fijos
 * - Se agregan campos para tracking de pallets
 * - Se elimina el umbral de 648 kg
 */

require_once 'db.php';

try {
    echo "<h2>Migrando Sistema Sampi a V2...</h2>";

    // 1. Agregar columna sampi_pallets para guardar detalle de pallets por código
    echo "<h3>1. Agregando columna sampi_pallets...</h3>";
    $pdo->exec("
        ALTER TABLE Bookings
        ADD COLUMN IF NOT EXISTS sampi_pallets JSON DEFAULT NULL
        COMMENT 'Detalle de pallets por código: {\"1011\": {\"units\": 1000, \"pallets\": 2, \"minutes\": 8}}'
    ");
    echo "<p>✓ Columna sampi_pallets agregada.</p>";

    // 2. Modificar sampi_time para que sea nullable y representar minutos totales de Sampi
    echo "<h3>2. Modificando columna sampi_time...</h3>";
    $pdo->exec("
        ALTER TABLE Bookings
        MODIFY COLUMN sampi_time INT DEFAULT NULL
        COMMENT 'Tiempo total de Sampi en minutos (suma de todos los pallets × 4 min)'
    ");
    echo "<p>✓ Columna sampi_time modificada.</p>";

    // 3. Agregar índice compuesto para búsquedas eficientes del acumulado Sampi del día
    echo "<h3>3. Agregando índices...</h3>";
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_date_resource
        ON Bookings(date, resourceId)
    ");
    echo "<p>✓ Índice idx_date_resource creado.</p>";

    // 4. Crear tabla de configuración de pallets Sampi (referencia)
    echo "<h3>4. Creando tabla SampiPalletConfig...</h3>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS SampiPalletConfig (
            code VARCHAR(50) PRIMARY KEY,
            units_per_pallet INT NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Configuración de unidades por pallet para productos Sampi'
    ");
    echo "<p>✓ Tabla SampiPalletConfig creada.</p>";

    // 5. Insertar configuración de pallets inicial
    echo "<h3>5. Insertando configuración de pallets...</h3>";
    $sampiConfig = [
        ['1011', 864, 'Producto 1011'],
        ['1014', 864, 'Producto 1014'],
        ['1015', 864, 'Producto 1015'],
        ['1016', 864, 'Producto 1016'],
        ['1059', 200, 'Producto 1059'],
        ['1063', 192, 'Producto 1063'],
        ['1066', 240, 'Producto 1066'],
    ];

    $stmt = $pdo->prepare("
        INSERT INTO SampiPalletConfig (code, units_per_pallet, description)
        VALUES (:code, :units, :desc)
        ON DUPLICATE KEY UPDATE
            units_per_pallet = VALUES(units_per_pallet),
            description = VALUES(description)
    ");

    foreach ($sampiConfig as [$code, $units, $desc]) {
        $stmt->execute([
            ':code' => $code,
            ':units' => $units,
            ':desc' => $desc
        ]);
    }
    echo "<p>✓ Configuración de " . count($sampiConfig) . " productos Sampi insertada.</p>";

    // 6. Resetear bookings Sampi existentes (opcional - comentado por seguridad)
    echo "<h3>6. Limpieza de datos antiguos...</h3>";
    echo "<p><strong>⚠ ADVERTENCIA:</strong> Los bookings existentes con lógica antigua seguirán funcionando.</p>";
    echo "<p>Si necesitas limpiar datos antiguos, ejecuta manualmente:</p>";
    echo "<pre>UPDATE Bookings SET sampi_on = 0, sampi_time = NULL, sampi_pallets = NULL WHERE sampi_on = 1;</pre>";

    echo "<h3>✅ Migración completada exitosamente.</h3>";
    echo "<h4>Próximos pasos:</h4>";
    echo "<ul>";
    echo "<li>Actualizar código backend (bulk-create.php, index.php)</li>";
    echo "<li>Actualizar código frontend (dashboard.html)</li>";
    echo "<li>Probar con nuevos pedidos</li>";
    echo "</ul>";

} catch (PDOException $e) {
    echo "<h2>❌ Error en la migración:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "<p>Código de error: " . $e->getCode() . "</p>";
}
?>
