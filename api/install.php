<?php
// api/install.php
require_once 'db.php';
require_once 'users/bootstrap.php';

echo "<h1>Instalación de Base de Datos (MySQL)</h1>";

try {
    // 1. Create Users Table
    $roles = bootstrap_roles();
    ensure_users_schema($pdo, $roles);
    echo "<p>Tabla 'Users' verificada/creada.</p>";

    // 2. Create Bookings Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS Bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client VARCHAR(255) NOT NULL,
        clientCode VARCHAR(50),
        orderNumber VARCHAR(50),
        description TEXT,
        kg DECIMAL(10,3) DEFAULT 0,
        duration INT NOT NULL,
        color VARCHAR(50) DEFAULT 'blue',
        resourceId VARCHAR(50) NOT NULL,
        date VARCHAR(20) NOT NULL,
        startTimeHour INT NOT NULL,
        startTimeMinute INT NOT NULL,
        realStartTime VARCHAR(10),
        realEndTime VARCHAR(10),
        status ENUM('PENDING', 'PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'BLOCKED') DEFAULT 'PENDING',
        priority ENUM('Normal', 'Urgente', 'Lista', 'Espera') DEFAULT 'Normal',
        observations TEXT,
        items JSON,
        sampi_time INT DEFAULT 0,
        sampi_on TINYINT(1) DEFAULT 0,
        is_blocked TINYINT(1) DEFAULT 0,
        blocked_by VARCHAR(50),
        blocked_reason TEXT,
        blocked_debt_amount DECIMAL(10,2),
        blocked_at TIMESTAMP NULL,
        prev_status VARCHAR(20),
        prev_resourceId VARCHAR(50),
        prev_color VARCHAR(50),
        real_start_at DATETIME NULL,
        createdBy INT,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (createdBy) REFERENCES Users(id) ON DELETE SET NULL
    )");
    echo "<p>Tabla 'Bookings' verificada/creada.</p>";

    // 2b. Create Booking Block Audit Table
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

    // 3. Create Products Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS Products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255),
        coefficient DECIMAL(10,3) NOT NULL DEFAULT 1.000,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<p>Tabla 'Products' verificada/creada.</p>";

    // 3c. Create Clients Table
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

    // 3b. Seed product coefficients (idempotente)
    $seed = [
        ['1001', 1.000], ['1003', 3.800], ['1004', 1.000], ['1006', 1.000], ['1010', 1.000],
        ['1011', 1.000], ['1014', 1.000], ['1016', 1.000], ['1017', 0.180], ['1036', 0.180],
        ['1050', 0.300], ['1015', 1.000], ['1018', 1.000], ['1019', 0.125], ['1020', 1.000],
        ['1021', 1.000], ['1022', 0.400], ['1024', 0.125], ['1025', 1.000], ['1026', 0.250],
        ['1027', 1.000], ['1028', 0.180], ['1030', 3.800], ['1031', 4.000], ['1032', 4.000],
        ['1034', 0.240], ['1035', 1.000], ['1039', 0.500], ['1040', 1.000], ['1041', 0.500],
        ['1043', 0.250], ['1044', 0.500], ['1045', 1.000], ['1053', 5.000], ['1054', 10.000],
        ['1055', 25.000], ['1056', 1.000], ['1059', 3.800], ['1061', 2.000], ['1063', 4.000],
        ['1066', 3.600], ['1067', 0.500], ['1068', 1.000], ['1069', 2.600], ['1070', 0.400],
        ['1071', 0.400], ['1073', 1.300], ['1074', 1.200], ['1078', 0.300], ['1086', 1.000],
        ['1087', 1.000], ['1091', 2.000], ['1097', 0.500], ['1098', 1.000], ['1134', 0.200],
        ['1139', 0.400], ['1143', 0.200], ['1144', 0.400], ['1148', 10.000], ['1151', 5.000],
        ['1827', 1.000], ['1863', 4.200], ['2011', 1.000], ['1013D', 0.125], ['1013F', 0.125],
        ['1013V', 0.125], ['1018D', 1.000], ['1018F', 1.000], ['1018M', 1.000], ['1018V', 1.000],
        ['1019F', 0.125], ['1019V', 0.125], ['1025D', 1.000], ['1025F', 1.000], ['1025V', 1.000],
        ['1026F', 0.250], ['1026V', 0.250], ['1027B', 1.000], ['1027D', 1.000], ['1027F', 1.000],
        ['1027V', 1.000], ['1028F', 0.180], ['1028V', 0.180], ['1827D', 1.000], ['1827F', 1.000],
        ['1827T', 1.000], ['1088', 1.000], ['1859', 3.800], ['1891', 4.000], ['1893', 4.000],
        ['1894', 1.200], ['1024F', 0.125], ['1024V', 0.125], ['1026B', 1.000], ['1035D', 1.000],
        ['1035F', 1.000], ['1035V', 1.000],
    ];

    $stmt = $pdo->prepare("INSERT INTO Products (code, coefficient) VALUES (:code, :coef)
        ON DUPLICATE KEY UPDATE coefficient = VALUES(coefficient)");
    foreach ($seed as [$code, $coef]) {
        $stmt->execute([':code' => $code, ':coef' => $coef]);
    }
    echo "<p>Productos iniciales cargados/actualizados.</p>";

    $created = ensure_admin_exists($pdo);
    if ($created) {
        echo "<p>Usuario 'admin' (password: admin123) creado.</p>";
    } else {
        echo "<p>El usuario 'admin' ya existe.</p>";
    }

    echo "<h3>Instalación completada con éxito. Por favor, borra este archivo (api/install.php) del servidor.</h3>";

} catch (PDOException $e) {
    echo "<h2>Error en la instalación:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
}
?>
