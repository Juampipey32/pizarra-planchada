// AppConfig - Configuración centralizada de la aplicación
const AppConfig = {
    // API Base URL
    API_URL: '/api/bookings',

    // Configuración de horarios
    START_HOUR: 4,  // 4:00 AM
    END_HOUR: 20,   // 8:00 PM
    SLOT_DURATION: 30, // minutos por slot

    // Configuración Sampi
    // El sistema Sampi transporta productos automáticamente mediante pallets
    // Cada pallet tarda 4 minutos en buscarse y transportarse
    SAMPI: {
        CODES: ['1011', '1014', '1015', '1016', '1059', '1063', '1066'], // Códigos transportados por Sampi
        MINUTES_PER_PALLET: 4, // Tiempo fijo por pallet
        UNITS_PER_PALLET: {
            '1011': 864,
            '1014': 864,
            '1015': 864,
            '1016': 864,
            '1059': 200,
            '1063': 192,
            '1066': 240
        }
    },

    // API Endpoints (Native - replaced n8n webhooks)
    API: {
        BOOKINGS: '/api/bookings',
        PENDING: '/api/bookings/pending.php',
        PDF_UPLOAD: '/api/bookings/upload-pdf.php',
        BULK_UPLOAD: '/api/bookings/bulk-upload.php',
        UNMET_DEMAND: '/api/unmet-demand',
        DEVIATIONS: '/api/deviations'
    },

    // Coeficientes de productos (código => coeficiente)
    // Estos valores se usan para calcular kg = cantidad * coeficiente
    COEFFICIENTS: {
        '1001': 1.000, '1003': 3.800, '1004': 1.000, '1006': 1.000, '1010': 1.000,
        '1011': 1.000, '1014': 1.000, '1016': 1.000, '1017': 0.180, '1036': 0.180,
        '1050': 0.300, '1015': 1.000, '1018': 1.000, '1019': 0.125, '1020': 1.000,
        '1021': 1.000, '1022': 0.400, '1024': 0.125, '1025': 1.000, '1026': 0.250,
        '1027': 1.000, '1028': 0.180, '1030': 3.800, '1031': 4.000, '1032': 4.000,
        '1034': 0.240, '1035': 1.000, '1039': 0.500, '1040': 1.000, '1041': 0.500,
        '1043': 0.250, '1044': 0.500, '1045': 1.000, '1053': 5.000, '1054': 10.000,
        '1055': 25.000, '1056': 1.000, '1059': 3.800, '1061': 2.000, '1063': 4.000,
        '1066': 3.600, '1067': 0.500, '1068': 1.000, '1069': 2.600, '1070': 0.400,
        '1071': 0.400, '1073': 1.300, '1074': 1.200, '1078': 0.300, '1086': 1.000,
        '1087': 1.000, '1091': 2.000, '1097': 0.500, '1098': 1.000, '1134': 0.200,
        '1139': 0.400, '1143': 0.200, '1144': 0.400, '1148': 10.000, '1151': 5.000,
        '1827': 1.000, '1863': 4.200, '2011': 1.000, '1013D': 0.125, '1013F': 0.125,
        '1013V': 0.125, '1018D': 1.000, '1018F': 1.000, '1018M': 1.000, '1018V': 1.000,
        '1019F': 0.125, '1019V': 0.125, '1025D': 1.000, '1025F': 1.000, '1025V': 1.000,
        '1026F': 0.250, '1026V': 0.250, '1027B': 1.000, '1027D': 1.000, '1027F': 1.000,
        '1027V': 1.000, '1028F': 0.180, '1028V': 0.180, '1827D': 1.000, '1827F': 1.000,
        '1827T': 1.000, '1088': 1.000, '1859': 3.800, '1891': 4.000, '1893': 4.000,
        '1894': 1.200, '1024F': 0.125, '1024V': 0.125, '1026B': 1.000, '1035D': 1.000,
        '1035F': 1.000, '1035V': 1.000,
    },

    // Configuración de cálculo de duración
    KG_PER_HOUR: 2000, // kg procesados por hora
    MIN_DURATION: 30,  // duración mínima en minutos

    // Estados de bookings
    STATUSES: {
        PENDING: 'PENDING',
        PLANNED: 'PLANNED',
        IN_PROGRESS: 'IN_PROGRESS',
        COMPLETED: 'COMPLETED',
        CANCELLED: 'CANCELLED'
    },

    // Prioridades
    PRIORITIES: {
        NORMAL: 'Normal',
        URGENT: 'Urgente',
        READY: 'Lista',
        WAITING: 'Espera'
    },

    // Roles de usuario
    ROLES: {
        ADMIN: 'ADMIN',
        VENDEDOR: 'VENDEDOR',
        INVITADO: 'INVITADO',
        PLANCHADA: 'PLANCHADA',
        VISUALIZADOR: 'VISUALIZADOR'
    },

    // Bloqueadores permitidos (selector fijo)
    BLOCKERS: ['JUAMPI', 'MAURICIO', 'SANDRA']
};

// Hacer disponible globalmente
if (typeof window !== 'undefined') {
    window.AppConfig = AppConfig;
}

// Soporte para módulos ES6 (si se usa en el futuro)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AppConfig;
}
