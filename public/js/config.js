// public/js/config.js

const AppConfig = {
    // API Base URL (relative or absolute)
    API_URL: '/api/bookings',

    // Webhook Handlers (Proxied or Direct)
    // Note: Ideally these should be proxied via backend to hide URLs, 
    // but for now we keep existing behavior with centralized constants.
    WEBHOOKS: {
        PDF_UPLOAD: '/api/n8n-proxy.php?action=upload_pdf',
        LOAD_MASSIVE: '/api/n8n-proxy.php?action=load',
        READ_DATA: '/api/n8n-proxy.php?action=read'
    },

    // Configuración Sampi
    SAMPI: {
        CODES: ['1011', '1015', '1016'],
        THRESHOLD_KG: 648
    },

    // Coeficientes
    COEFFICIENTS: {
        "1003": 4.00, "1010": 1.00, "1011": 1.00, "1013": 0.13, "1014": 1.00,
        "1015": 1.00, "1016": 1.00, "1018": 1.00, "1019": 0.13, "1020": 1.00,
        "1021": 1.00, "1022": 0.70, "1025": 1.00, "1026": 0.25, "1027": 1.00,
        "1028": 0.18, "1029": 1.00, "1031": 4.00, "1036": 0.18, "1040": 1.00,
        "1045": 1.00, "1050": 0.30, "1053": 5.00, "1054": 10.00, "1055": 25.00,
        "1056": 1.00, "1059": 3.80, "1061": 2.00, "1063": 4.20, "1066": 2.50,
        "1067": 0.60, "1068": 1.10, "1069": 3.00, "1070": 0.70, "1071": 0.70,
        "1073": 1.30, "1074": 1.20, "1078": 0.30, "1086": 1.00, "1088": 1.00,
        "1091": 2.00, "1097": 0.60, "1098": 1.10, "1134": 0.20, "1139": 0.40,
        "1143": 0.20, "1144": 0.40, "1148": 10.00, "1151": 10.00, "1827": 1.00,
        "1859": 4.00, "1863": 4.20, "1890": 4.20, "1891": 2.00, "1893": 4.00,
        "1894": 1.20, "1991": 25.00
    },

    // Global Settings
    START_HOUR: 4,
    END_HOUR: 20,
    SLOT_DURATION: 30
};

window.AppConfig = AppConfig;
