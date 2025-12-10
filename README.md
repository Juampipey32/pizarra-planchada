# 📋 Pizarra Planchada

> Sistema de gestión interactiva de pedidos y productos con panel de administración

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue.svg)](https://www.php.net/)

## 🚀 Descripción

**Pizarra Planchada** es una aplicación web completa para la gestión de pedidos y productos en tiempo real. Diseñada para negocios que necesitan una interfaz visual e intuitiva para administrar reservas, productos y mantener un control centralizado de sus operaciones.

### ✨ Características Principales

- **Dashboard Interactivo**: Visualización en tiempo real de pedidos y estado de productos
- **Gestión de Productos**: Panel de administración completo para crear, editar y eliminar productos
- **Sistema de Reservas**: Gestión de bookings con fechas y horarios
- **Autenticación Segura**: Sistema de login con JWT (JSON Web Tokens)
- **API RESTful**: Endpoints organizados para todas las operaciones CRUD
- **Responsive Design**: Interfaz adaptable a dispositivos móviles y desktop
- **CORS Configurado**: Listo para integraciones con otras aplicaciones
- **Deploy Automático**: CI/CD configurado con GitHub Actions

## 🚀 Deploy Automático

Este proyecto cuenta con deploy automático a **pizarra-ventas.socialsflow.io** mediante GitHub Actions.

Cada push a las ramas configuradas deploya automáticamente via SSH a Hostinger.

📖 **[Ver guía completa de configuración](.github/DEPLOY.md)**

### Quick Start:
1. Configura los secrets en GitHub (SSH_HOST, SSH_PORT, SSH_USERNAME, SSH_PRIVATE_KEY, REMOTE_PATH)
2. Haz push a la rama
3. ¡Listo! El sitio se actualiza automáticamente en 1-2 minutos

## 🏗️ Arquitectura

```
pizarra-planchada/
├── api/
│   ├── auth/              # Endpoints de autenticación
│   ├── bookings/          # Gestión de reservas
│   ├── products/          # CRUD de productos
│   ├── cors.php           # Configuración CORS
│   ├── db.php             # Conexión a base de datos
│   ├── jwt_helper.php     # Helpers para JWT
│   └── install.php        # Script de instalación
├── public/
│   ├── index.html         # Página de login
│   ├── dashboard.html     # Panel principal
│   └── admin-products.html # Administración de productos
├── PEDIDOS-PIZARRA/       # Directorio de pedidos
└── .htaccess              # Configuración Apache
```

## 🛠️ Tecnologías

### Backend
- **PHP 7.4+**: Lógica del servidor
- **MySQL/MariaDB**: Base de datos relacional
- **JWT**: Autenticación basada en tokens
- **Apache**: Servidor web con mod_rewrite

### Frontend
- **HTML5/CSS3**: Estructura y estilos
- **JavaScript Vanilla**: Interactividad sin frameworks pesados
- **Fetch API**: Comunicación con el backend

## 📦 Instalación

### Requisitos Previos

- PHP >= 7.4
- MySQL/MariaDB >= 5.7
- Apache con mod_rewrite habilitado
- Composer (opcional, para dependencias)

### Pasos de Instalación

1. **Clonar el repositorio**
```bash
git clone https://github.com/Juampipey32/pizarra-planchada.git
cd pizarra-planchada
```

2. **Configurar la base de datos**

Edita el archivo `api/db.php` con tus credenciales o define las variables de entorno `DB_HOST`, `DB_NAME`, `DB_USER` y `DB_PASS` (también puedes crear `api/config.php` para sobrescribirlas). El sistema intentará usar las variables primero y luego los valores por defecto.

3. **Ejecutar script de instalación**

Navega a:
```
http://tu-dominio.com/api/install.php
```

Este script creará automáticamente las tablas necesarias.

4. **Configurar permisos**

```bash
chmod 755 PEDIDOS-PIZARRA/
chmod 644 api/*.php
```

5. **Configurar JWT Secret**

En `api/jwt_helper.php`, modifica la clave secreta:

```php
private $secret_key = 'TU_CLAVE_SECRETA_AQUI';
```

## 🚦 Uso

### Acceso al Sistema

1. Abre tu navegador y navega a `http://tu-dominio.com`
2. Usa las credenciales creadas durante la instalación (si no existen usuarios, el sistema auto-creará `admin / admin123` con rol ADMIN)
3. Accede al dashboard principal

### Gestión de Productos

- Navega a "Administrar Productos" desde el menú principal
- Añade nuevos productos con nombre, precio y descripción
- Edita o elimina productos existentes
- Los cambios se reflejan inmediatamente en el dashboard

### Gestión de Reservas

- Visualiza todas las reservas en el dashboard
- Crea nuevas reservas con fecha y horario
- Marca reservas como completadas o canceladas

## 🔌 API Endpoints

### Autenticación

```
POST /api/auth/login.php
POST /api/auth/register.php
POST /api/auth/logout.php
```

### Productos

```
GET    /api/products/list.php        # Listar todos los productos
GET    /api/products/get.php?id=1    # Obtener un producto
POST   /api/products/create.php      # Crear producto
PUT    /api/products/update.php      # Actualizar producto
DELETE /api/products/delete.php      # Eliminar producto
```

### Reservas (Bookings)

```
GET    /api/bookings/list.php        # Listar reservas
POST   /api/bookings/create.php      # Crear reserva
PUT    /api/bookings/update.php      # Actualizar reserva
DELETE /api/bookings/delete.php      # Eliminar reserva
```

## 🔐 Seguridad

- **JWT Authentication**: Todos los endpoints protegidos requieren un token válido
- **Password Hashing**: Las contraseñas se almacenan con `password_hash()`
- **Prepared Statements**: Protección contra SQL Injection
- **CORS Configurado**: Control de acceso desde diferentes dominios
- **HTTPS Recomendado**: Para producción, siempre usa certificados SSL

## 🎨 Personalización

### Estilos

Los estilos están embebidos en cada archivo HTML. Para personalizar:

1. Modifica las variables CSS en la sección `<style>` de cada página
2. Cambia colores, fuentes y espaciados según tu marca

### Logo y Branding

Reemplaza los elementos de marca en:
- `public/index.html` - Pantalla de login
- `public/dashboard.html` - Header del dashboard

## 📱 Responsive Design

La aplicación está optimizada para:
- 📱 Móviles (320px - 480px)
- 📱 Tablets (481px - 768px)
- 💻 Desktop (769px+)

## 🤝 Contribuir

¡Las contribuciones son bienvenidas! Para contribuir:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

## 📝 Roadmap

- [ ] Implementar notificaciones en tiempo real (WebSockets)
- [ ] Añadir sistema de roles y permisos
- [ ] Dashboard de analytics y reportes
- [ ] Exportación de datos a Excel/PDF
- [ ] Integración con sistemas de pago
- [ ] App móvil nativa (React Native)
- [ ] Sistema de inventario avanzado

## 🐛 Reportar Problemas

Si encuentras algún bug o tienes sugerencias:

1. Verifica que no exista un issue similar
2. Crea un nuevo issue con descripción detallada
3. Incluye pasos para reproducir el problema
4. Agrega capturas de pantalla si es relevante

## 📄 Licencia

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para más detalles.

## 👨‍💻 Autor

**Juampipey32**

- GitHub: [@Juampipey32](https://github.com/Juampipey32)
- Website: [pizarra-ventas.socialsflow.io](https://pizarra-ventas.socialsflow.io)

## 🙏 Agradecimientos

- A la comunidad de PHP por las excelentes librerías
- A todos los contribuidores que mejoran este proyecto
- A los usuarios que reportan bugs y sugieren mejoras

---

⭐ Si este proyecto te fue útil, considera darle una estrella en GitHub!

🔗 **Demo**: [https://pizarra-ventas.socialsflow.io](https://pizarra-ventas.socialsflow.io)