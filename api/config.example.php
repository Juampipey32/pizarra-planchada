<?php
// api/config.php
// Ejemplo de configuración segura.

// Base de Datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'u363074645_pizarra_ventas');
define('DB_USER', 'tu_usuario_db'); // CAMBIAR
define('DB_PASS', 'tu_password_db'); // CAMBIAR

// Seguridad JWT (IMPORTANTE: CAMBIAR ESTO)
define('JWT_SECRET', 'cambia_esta_clave_por_una_muy_larga_y_segura_123456');

// Webhooks (Opcional)
define('WEBHOOK_READ_DATA', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/DATOS_FRONT');
define('WEBHOOK_LOAD_MASSIVE', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/carga_masiva');
define('WEBHOOK_PDF_UPLOAD', 'https://juampi-agente-n8n.h1yobv.easypanel.host/webhook/PEDIDOS-COSALTA');

// Otras configuraciones
define('DEV_MODE', false);
?>
