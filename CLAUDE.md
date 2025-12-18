# CLAUDE.md

Este archivo proporciona guía a Claude Code (claude.ai/code) cuando trabaja con código en este repositorio.

## 1. Comandos de desarrollo comunes

Este proyecto es una aplicación PHP vanilla sin gestores de paquetes tradicionales. Los comandos de desarrollo principales son:

### Scripts PHP disponibles:
- **Instalación de base de datos**: `http://tu-dominio.com/api/install.php` (crea tablas y seeds)
- **Limpieza de caché**: `/api/clear_cache.php`
- **Debug de entorno**: `/api/debug_env.php` (muestra configuración y variables)
- **Actualización de esquema**: `/api/update_schema.php`
- **Corregir usuario admin**: `/api/users/fix-admin.php`
- **Bootstrap de usuarios**: `/api/users/bootstrap.php` (asegura esquema de BD)
- **Webhook n8n listener**: `/api/bookings/n8n-listener.php` (endpoint para inserción automática)
- **CSV Upload**: `/api/bookings/bulk-upload.php` (procesamiento masivo)
- **Bulk Creation**: `/api/bookings/bulk-create.php` (creación masiva con validación)
- **PDF Upload**: `/api/bookings/upload-pdf.php` (extracción nativa de PHP)

### Para desarrollo local:
1. Configurar Apache con mod_rewrite habilitado
2. Configurar base de datos MySQL/MariaDB
3. Navegar a `api/install.php` para inicializar la BD
4. Usar `api/debug_env.php` para verificar configuración
5. Activar `DEV_MODE` en `settings.php` para bypass de autenticación durante desarrollo

### Endpoints API clave:
- **Autenticación**: `/api/auth/login.php`, `/api/auth/register.php`
- **Bookings**: `/api/bookings/index.php`, `/api/bookings/manage.php`
- **Productos**: `/api/products/index.php`, `/api/products/manage.php`
- **Usuarios**: `/api/users/index.php`, `/api/users/manage.php`

### Características de UI/UX:
- **Drawer lateral**: Se abre al pasar mouse cerca del borde izquierdo
- **Kanban Board**: Drag & drop con SortableJS
- **Filtros inteligentes**: Por fecha, estado, y Sampi
- **Efectos 3D**: CSS transforms con sombras múltiples
- **DatePicker integrado**: Navegación entre días

## 2. Arquitectura de alto nivel

### Estructura general:
```
api/                          # Backend PHP sin framework
├── bookings/               # Sistema de reservas (18 archivos PHP)
│   ├── index.php           # Listar bookings
│   ├── manage.php          # Gestionar booking individual
│   ├── upload.php          # Subir archivos
│   ├── upload-pdf.php      # Procesar PDFs
│   ├── bulk-*.php          # Operaciones masivas
│   ├── helpers.php         # Funciones auxiliares
│   ├── n8n-listener.php    # Webhook para n8n
│   └── check-duplicates.php # Verificar duplicados
├── products/               # Gestión de productos
├── users/                  # Gestión de usuarios
├── auth/                   # Autenticación JWT
└── config.example.php      # Configuración

public/                     # Frontend estático
├── index.html             # Login
├── dashboard.html         # Dashboard principal (Kanban Board)
├── admin-products.html    # Admin productos
├── admin-users.html      # Admin usuarios
└── js/config.js           # Configuración frontend

PEDIDOS-PIZARRA/           # Directorios de pedidos PDF
n8n-flujos/               # Flujos de n8n
```

### Componentes principales:
- **API RESTful**: 34 endpoints organizados por dominio (bookings, products, auth, users)
- **Sistema de reservas avanzado**: Con detección de solapamientos, cálculo automático de duración, y múltiples estados
- **Parser de PDFs nativo**: Integración con n8n para procesamiento de pedidos
- **Autenticación JWT con roles**: 5 roles diferentes (ADMIN, VENDEDOR, INVITADO, PLANCHADA, VISUALIZADOR)
- **Panel de administración**: Interfaz con Kanban Board y drag & drop
- **Webhooks**: Integración con n8n para automatización de flujos
- **Sistema Sampi**: Integración especial para productos específicos con umbral de 648 kg

### Organización:
- **Backend**: PHP vanilla organizado por dominio funcional
- **Frontend**: Archivos HTML estáticos con JavaScript embebido y UI avanzada
- **Configuración**: Separada entre frontend (config.js) y backend
- **Integraciones**: Webhooks externos para procesamiento PDF y Sheets
- **Herramientas de desarrollo**: Scripts PHP para instalación, debug y mantenimiento

### Características clave no documentadas previamente:
- **Múltiples roles de usuario**: Control de acceso por rol
- **Detección automática de solapamientos**: Función `findOverlap()` en helpers.php
- **Cálculo de duración basado en peso**: 2,000 kg/hora con bloques de 30 minutos
- **Sistema de estados**: PENDING, PLANNED, IN_PROGRESS, COMPLETED, CANCELLED
- **Integración Sampi**: Productos especiales con división automática
- **Drawer lateral animado**: UI avanzada con efectos 3D
- **Filtros inteligentes**: Por fecha, estado, y Sampi
- **Procesamiento masivo**: CSV upload, bulk creation, PDF upload
- **Webhook n8n listener**: Endpoint para inserción automática de bookings
- **Variables de entorno**: DEV_MODE, JWT_SECRET, WEBHOOK_SHEETS

### Integración n8n (Actualizado Dic 2025):
- **Flujo de datos**: Excel → n8n (carga_masiva) → Tabla PROD PICKING → n8n (DATOS_FRONT) → Proxy PHP → Dashboard
- **Proxy CORS**: `api/n8n-proxy.php` reenvía requests a n8n evitando bloqueos de navegador
- **Parseo de items**: `parseDetailToItems()` extrae productos del string `DETALLE_PEDIDO`
- **Method Override**: Hostinger bloquea PUT/DELETE, se usa POST + header `X-HTTP-Method-Override`
- **Webhooks n8n**:
  - `carga_masiva`: Recibe Excel, extrae datos, calcula KG por producto
  - `DATOS_FRONT`: Devuelve todos los pedidos de la tabla

## 3. Configuraciones importantes

### Backend (PHP):
- **Base de datos**: MySQL/MariaDB con tablas `Users`, `Bookings`, `Products` con JSON storage para items
- **JWT Secret**: Configurable en `jwt_helper.php` o variable de entorno
- **CORS**: Configuración en `cors.php`
- **Webhooks**: URLs de n8n en `config.example.php`
- **Modo Desarrollo**: Feature flag `DEV_MODE` en `settings.php` para bypass de autenticación
- **Variables de entorno**: DB_HOST, DB_NAME, DB_USER, DB_PASS, JWT_SECRET, WEBHOOK_SHEETS, DEV_MODE

### Frontend (JavaScript):
- **AppConfig** (`public/js/config.js`): Contiene URLs de API, webhooks, y configuraciones específicas
- **Horarios**: Configurado de 4:00 a 20:00 (configurable)
- **Coeficientes por código**: Mapeo completo con 42 productos únicos
- **Umbral Sampi**: 648 kg (automáticamente divide el pedido)
- **Duración por defecto**: 30 minutos mínimo
- **Estado visual**: Colores por estado (blue=Normal, red=Urgente, green=Lista, orange=Espera)

### Variables de entorno:
```bash
WEBHOOK_IMPORT=https://n8n-n8n.rbmlzu.easypanel.host/webhook/PEDIDOS-COSALTA
WEBHOOK_SHEETS=https://n8n-n8n.rbmlzu.easypanel.host/webhook/GUARDAR-SHEET
JWT_SECRET=tu_secreto_jwt
DEV_MODE=false
```

### .htaccess:
- Rewrite rules para rutas limpias
- Redirección de raíz a public/index.html
- URLs amigables para la API (imitando paths de Node.js)

### Esquema de Base de Datos:
- **Users**: Con sistema de roles y ENUM
- **Bookings**: 17 campos incluyendo JSON para items
- **Products**: Con sistema de coeficientes para cálculo de peso

### Parámetros Clave:
- **Duración cálculo**: 2,000 kg/hora con bloques de 30 minutos
- **Productos Sampi**: Códigos `['1011', '1015', '1016']` con tratamiento especial
- **División automática**: Si un pedido excede el umbral Sampi (648 kg)

## 4. Despliegue y CI/CD

### Despliegue automático:
- **URL de despliegue**: pizarra-ventas.socialsflow.io
- **CI/CD**: GitHub Actions configurado
- **Despliegue**: Via SSH a Hostinger
- **Tiempo de actualización**: 1-2 minutos después de push

### Flujos de n8n:
- **PEDIDOS-COSALTA**: Webhook para parsing de PDFs
- **GUARDAR-SHEET**: Webhook para sincronización con Google Sheets

## 5. Notas de desarrollo

- No hay comandos de build o test tradicionales (npm, composer)
- Desarrollo directo en servidor local con PHP
- Cambios en PHP requieren recargar Apache
- Cambios en frontend se actualizan directamente
- Integración con n8n para automatización de flujos de trabajo