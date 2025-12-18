# Repository Guidelines

## Estructura del proyecto
- `api/`: backend PHP vanilla. Módulos por dominio (`auth/`, `bookings/`, `products/`, `users/`). Patrón: `index.php` para listar y `manage.php` para crear/editar/borrar. Helpers compartidos en `api/bookings/helpers.php` y utilidades en `api/lib/`.
- `public/`: frontend estático (HTML/CSS/JS). Configuración cliente en `public/js/config.js`.
- `PEDIDOS-PIZARRA/`: almacenamiento de PDFs/pedidos procesados.
- `n8n-flujos/`: flujos de n8n usados por webhooks.
- `.env`: variables locales (ignorado por git).

## Comandos de desarrollo
No hay build tradicional (sin npm/composer). Para levantar local:
1. Apache + PHP 7.4+ con `mod_rewrite` y `.htaccess`.
2. Inicializa BD: abre `http://localhost/api/install.php`.

Comandos útiles:
- `http://localhost/api/debug_env.php`: verifica configuración/env.
- `http://localhost/api/clear_cache.php`: limpia caché/temporales.
- `http://localhost/api/update_schema.php`: migra columnas/tablas.

Opcional para pruebas rápidas de UI: `php -S localhost:8000 -t public` (sin rewrites).

## Estilo y convenciones
- Indentación: 4 espacios en PHP/JS.
- PHP: funciones y variables en `camelCase`, endpoints pequeños y orientados a dominio; usa `PDO` con prepared statements.
- Archivos: nombres en minúscula; usa guiones en scripts compuestos (`bulk-upload.php`).
- JS: `const/let`, punto y coma, no introducir frameworks sin acordarlo.

## Pruebas
No existe suite automatizada. Haz smoke tests manuales sobre endpoints y UI. Para validar lógica Sampi/duración usa `php test_calculation.php`. Añade casos de prueba reproducibles en la descripción del PR.

## Commits y Pull Requests
Historial actual usa mensajes cortos y descriptivos en Title Case (ej.: “Finalized App: …”). Mantén ese estilo: un resumen claro, opcionalmente con prefijo de tema (`Bookings: …`).

En PRs incluye: qué cambia, pasos para probar, screenshots si tocás UI, y notas de esquema/variables nuevas (actualiza `api/config.example.php`/`README.md`).

## Seguridad y configuración
Nunca subas secretos ni URLs privadas de webhooks. Usa `.env` para credenciales (`DB_*`, `JWT_SECRET`, `DEV_MODE`) y documenta cualquier nueva variable.

