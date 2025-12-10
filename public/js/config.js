// public/js/config.js

const AppConfig = {
    // API Base URL (relative or absolute)
    API_URL: '/api/bookings',

    // Webhook Handlers (Proxied or Direct)
    // Note: Ideally these should be proxied via backend to hide URLs, 
    // but for now we keep existing behavior with centralized constants.
    WEBHOOKS: {
        PDF_UPLOAD: 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/PEDIDOS-COSALTA',
        SHEET_SYNC: 'https://n8n-n8n.rbmlzu.easypanel.host/webhook/GUARDAR-SHEET'
    },

    // Global Settings
    START_HOUR: 4,
    END_HOUR: 20,
    SLOT_DURATION: 30
};

window.AppConfig = AppConfig;
