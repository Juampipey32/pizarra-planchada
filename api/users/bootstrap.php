<?php
// api/users/bootstrap.php
// Helpers to ensure Users table schema and a default admin exist, even if install.php wasn't run.

function bootstrap_roles(): array {
    return ['ADMIN', 'VENTAS', 'PLANCHADA'];
}

/**
 * Ensure Users table exists with the expected role enum.
 */
function ensure_users_schema(PDO $pdo, array $roles): void {
    $enum = "'" . implode("','", $roles) . "'";
    $pdo->exec("CREATE TABLE IF NOT EXISTS Users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM($enum) NOT NULL,
        createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    try {
        $pdo->exec("ALTER TABLE Users MODIFY role ENUM($enum) NOT NULL");
    } catch (PDOException $e) {
        // Ignore if alter not required or lacks permissions
    }
}

/**
 * Seed a default admin user if none exist.
 */
function ensure_admin_exists(PDO $pdo): bool {
    $stmt = $pdo->query("SELECT COUNT(*) as c FROM Users WHERE role = 'ADMIN'");
    $hasAdmin = intval($stmt->fetch()['c'] ?? 0) > 0;
    if ($hasAdmin) {
        return false;
    }

    $passHash = password_hash('admin123', PASSWORD_BCRYPT);
    $stmt = $pdo->prepare("INSERT INTO Users (username, password, role) VALUES ('admin', :pass, 'ADMIN')");
    try {
        $stmt->execute([':pass' => $passHash]);
        return true;
    } catch (PDOException $e) {
        // ignore if already created in a race
    }
    return false;
}
?>
