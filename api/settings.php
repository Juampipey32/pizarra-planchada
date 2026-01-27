<?php
// api/settings.php

// 1. Database Credentials
// Check if Env vars are set (e.g. via .env or server config), otherwise use defaults
defined('DB_HOST') or define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_NAME') or define('DB_NAME', getenv('DB_NAME') ?: 'u363074645_pizarra_ventas');
defined('DB_USER') or define('DB_USER', getenv('DB_USER') ?: 'u363074645_pizarra');
defined('DB_PASS') or define('DB_PASS', getenv('DB_PASS') ?: 'Juampindonga32-');

// 2. Security
// Change this to a strong random string in production!
defined('JWT_SECRET') or define('JWT_SECRET', getenv('JWT_SECRET') ?: 'secret_key_change_me');

// 3. Webhooks & Integrations
defined('SHEET_WEBHOOK_URL') or define('SHEET_WEBHOOK_URL', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/GUARDAR-SHEET');
defined('PDF_WEBHOOK_URL') or define('PDF_WEBHOOK_URL', 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/PEDIDOS-COSALTA');

// 4. Development Mode
// Set to true to bypass login checks (SYSTEM user fallback)
defined('DEV_MODE') or define('DEV_MODE', false);

// 5. Native Native Logic (No n8n, Deterministic)
// No API Keys required.

?>
