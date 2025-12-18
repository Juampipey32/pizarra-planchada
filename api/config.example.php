<?php
// api/config.example.php
// EJEMPLO de archivo de configuración
// Copiar este archivo como api/config.php y completar con tus valores

// ==========================================
// Webhooks n8n
// ==========================================

// Webhook para carga masiva de pedidos (Excel)
define('WEBHOOK_LOAD_MASSIVE', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/carga_masiva');

// Webhook para leer datos de la tabla PROD PICKING
define('WEBHOOK_READ_DATA', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/DATOS_FRONT');

// Webhook n8n para parsing de PDFs (legacy)
define('WEBHOOK_IMPORT', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/PEDIDOS-COSALTA');

// Webhook para sincronizar con Google Sheets (opcional)
define('WEBHOOK_SHEETS', null);

// ==========================================
// Seguridad
// ==========================================

// JWT Secret
if (!getenv('JWT_SECRET')) {
    define('JWT_SECRET', 'cambia_esto_por_una_clave_secreta_segura');
}
