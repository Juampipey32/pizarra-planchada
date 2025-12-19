# Repository Guidelines

## Estructura del proyecto
- `api/`: backend PHP vanilla organizado por dominio (`auth/`, `bookings/`, `products/`, `users/`). Cada subcarpeta sigue el patron `index.php` (listar) + `manage.php` (crear/editar/borrar). Reutilizables en `api/bookings/helpers.php`, `api/lib/autoload.php` y `api/lib/pdf2text.php`. Scripts raiz clave: `settings.php`, `install.php`, `clear_cache.php`, `debug_env.php`, `update_schema.php`, `n8n-proxy.php`, `test.php`, `cors.php` y `phpspreadsheet.zip` (dependencia manual, no composer).
- `public/`: frontend estatico (HTML/CSS/JS) con vistas `index.html`, `dashboard.html`, `admin-products.html`, `admin-users.html`, `bulk-upload.html` y `test-bulk-upload.html`. Configuracion del cliente en `public/js/config.js`; estilos basicos dentro de cada HTML y en `public/css/`.
- `PEDIDOS-PIZARRA/`: almacenamiento de PDFs y planillas procesadas. Mantener solo archivos necesarios para pruebas; no versionar datos reales.
- `n8n-flujos/`: definiciones JSON/CSV usadas por los flujos en n8n/make. Sanitiza credenciales o IDs sensibles antes de subir.
- Archivos raiz: `.htaccess` (rewrite Apache), `cors.php` (CORS standalone), `test_calculation.php`, `CLAUDE.md` (notas de stack) y este `AGENTS.md`.
- `.env`: variables locales, ignoradas por git. Usa `api/config.example.php` como referencia y documenta variaciones.

## Configuracion y comandos utiles
No hay build tradicional (sin npm/composer). Para levantar local:
1. Apache + PHP 7.4+ con `mod_rewrite` y soporte `.htaccess`.
2. Configura la base (`api/db.php` o variables `DB_*`) y corre `http://localhost/api/install.php` para sembrar tablas/usuario admin.

Herramientas disponibles:
- `http://localhost/api/debug_env.php`: imprime configuracion cargada.
- `http://localhost/api/clear_cache.php`: limpia temporales.
- `http://localhost/api/update_schema.php`: sincroniza columnas/cambios menores.
- `php -S localhost:8000 -t public`: servidor rapido para revisar UI (sin rewrites ni endpoints bajo `/api`).
- `php test_calculation.php`: valida la logica de calculos Sampi/duracion.

## Estilo y convenciones
- PHP y JS con indentacion de 4 espacios. Variables/funciones en `camelCase`. Evita funciones globales innecesarias.
- Endpoints pequenos y orientados al dominio; toda consulta SQL debe ir por `PDO` + prepared statements. Valida `$_GET/$_POST` antes de usarlos.
- Archivos en minusculas; usar guiones si un nombre lleva varias palabras (`bulk-upload.php`).
- JS vanilla: `const/let`, punto y coma obligatorio, manejar fetch con `async/await` y `try/catch`. No introducir frameworks sin discutirlo.
- Mantener configuracion del cliente aislada en `public/js/config.js` (URLs base, modo debug, etc.).

## Dependencias y datos externos
- No usamos Composer ni npm. Si necesitas una libreria, incluyela como helper especifico y documenta por que (ej.: `phpspreadsheet.zip`).
- Los flujos en `n8n-flujos/` y planillas en `PEDIDOS-PIZARRA/` deben estar anonimizados o con datos de prueba.
- No toques `.claude/` ni `.env*` en commits; son locales.

## Pruebas
- No hay suite automatizada. Ejecuta smoke tests manuales para cada endpoint/API afectado y revisa la UI principal (`dashboard.html`, `admin-products.html`, upload masivo).
- Para casos complejos deja pasos reproducibles en el PR (inputs, usuario usado, respuesta esperada).
- Si cambias calculos o parseo de archivos, agrega el escenario en `test_calculation.php` y documenta como correrlo.

## Commits y Pull Requests
- Mensajes en Title Case y concisos; opcional prefijo (`Bookings: ...`, `Products: ...`). Mantener historial limpio.
- En cada PR describe: que cambia, como probarlo (pasos concretos), capturas si tocaste UI, y notas de nuevos esquemas o env vars. Recuerda actualizar `api/config.example.php`, `README.md` y este archivo si aplica.
- Antes de pushear revalida que no se colaron PDFs reales, dumps `.sql`, llaves o archivos grandes innecesarios.

## Seguridad y configuracion
- Nunca publiques URLs reales de webhooks (`webhooks.txt` debe contener solo placeholders o datos ofuscados). Si hay que compartir endpoints productivos, usarlos via `.env`.
- Cualquier credencial nueva va en `.env` (`DB_*`, `JWT_SECRET`, `DEV_MODE`, etc.) y se documenta en `api/config.example.php`.
- Para entornos sin login, podés setear `AUTH_DISABLED=true` (y opcionalmente `AUTH_DISABLED_ROLE`, `AUTH_DISABLED_USER`) para forzar que los endpoints acepten cualquier request como ADMIN.
- Forzar HTTPS en despliegues, validar `Origin` en CORS y expirar tokens JWT acorde a negocio.

## Checklist rapida antes de subir
- [ ] Ejecutaste `install.php`/`update_schema.php` si cambiaste esquema.
- [ ] Probaste uploads masivos y dashboard si tocaste bookings/productos.
- [ ] Limpiastes caches temporales (`clear_cache.php`) al terminar pruebas.
- [ ] Confirmaste que `.env`, `.claude/` y archivos locales siguen fuera de git.
