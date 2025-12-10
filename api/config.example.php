<?php
// api/config.example.php
// EJEMPLO de archivo de configuración
// Copiar este archivo como api/config.php y completar con tus valores

// Webhook n8n para parsing de PDFs
define('WEBHOOK_IMPORT', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/PEDIDOS-COSALTA');

// JWT Secret (opcional, si no está en environment)
if (!getenv('JWT_SECRET')) {
    define('JWT_SECRET', 'cambia_esto_por_una_clave_secreta_segura');
}

// Webhook para sincronizar con Google Sheets (opcional)
if (!getenv('WEBHOOK_SHEETS')) {
    define('WEBHOOK_SHEETS', null);
}
