# 🚀 Configuración de Deploy Automático

Este proyecto está configurado para deployar automáticamente a **pizarra-ventas.socialsflow.io** cuando se hace push a las ramas especificadas.

## 📋 Requisitos Previos

Necesitas configurar los siguientes **GitHub Secrets** en tu repositorio:

### Cómo agregar Secrets en GitHub:

1. Ve a tu repositorio en GitHub
2. Click en **Settings** (Configuración)
3. En el menú lateral, click en **Secrets and variables** → **Actions**
4. Click en **New repository secret**
5. Agrega cada uno de los siguientes secrets:

---

## 🔐 Secrets Requeridos

### `SSH_HOST`
- **Descripción**: Host SSH de Hostinger
- **Valor típico**: `srv123.main-hosting.eu` o similar
- **Dónde encontrarlo**: Panel de Hostinger → hPanel → Avanzado → SSH Access

### `SSH_PORT`
- **Descripción**: Puerto SSH de Hostinger
- **Valor típico**: `65002`
- **Dónde encontrarlo**: Panel de Hostinger → hPanel → Avanzado → SSH Access

### `SSH_USERNAME`
- **Descripción**: Tu usuario SSH (generalmente es tu usuario de hosting)
- **Valor típico**: `u123456789`
- **Dónde encontrarlo**: Panel de Hostinger → hPanel → Avanzado → SSH Access

### `SSH_PRIVATE_KEY`
- **Descripción**: Tu clave SSH privada (SSH Private Key)
- **Valor**: El contenido completo del archivo de clave privada SSH
- **Formato**:
  ```
  -----BEGIN OPENSSH PRIVATE KEY-----
  b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAABlwAAAAdzc2gtcn
  ...
  -----END OPENSSH PRIVATE KEY-----
  ```

#### ⚠️ Si aún no tienes una clave SSH:

**Opción 1: Generar desde tu computadora**
```bash
# En tu terminal local
ssh-keygen -t ed25519 -C "deploy@pizarra-planchada"
# Cuando pregunte dónde guardar, usa: ~/.ssh/hostinger_deploy
# NO pongas contraseña (deja en blanco para CI/CD)

# Ver la clave privada (esta va en SSH_PRIVATE_KEY)
cat ~/.ssh/hostinger_deploy

# Ver la clave pública (esta la agregas en Hostinger)
cat ~/.ssh/hostinger_deploy.pub
```

Luego agrega la clave pública en Hostinger:
1. Panel de Hostinger → hPanel → Avanzado → SSH Access
2. Sección "SSH Keys"
3. Click "Manage SSH Keys"
4. Pega el contenido de `hostinger_deploy.pub`

**Opción 2: Usar la clave SSH existente que ya tienes guardada**
- Si ya tienes SSH configurado, copia el contenido de tu clave privada

### `REMOTE_PATH`
- **Descripción**: Ruta completa en el servidor donde se encuentra tu sitio
- **Valor típico**: `/home/u123456789/domains/pizarra-ventas.socialsflow.io/public_html`
- **Formato completo**: `/home/[SSH_USERNAME]/domains/pizarra-ventas.socialsflow.io/public_html`

---

## 🔄 Cómo Funciona el Deploy

El workflow se activa automáticamente cuando:
- Haces `git push` a la rama `main`
- Haces `git push` a la rama `master`
- Haces `git push` a `claude/review-project-demo-018atWe9Dxp8inzeTaBRPgvy`

### Proceso de Deploy:

1. ✅ **Checkout**: Descarga el código del repositorio
2. 🔐 **Setup SSH**: Configura la conexión SSH segura
3. 📦 **Rsync**: Sincroniza archivos al servidor (solo cambios)
4. 🔧 **Post-deploy**: Configura permisos correctos
5. ✅ **Notificación**: Informa del resultado

### Archivos Excluidos del Deploy:

- `.git/` - Historial de git
- `.github/` - Workflows y configs de GitHub
- `node_modules/` - Dependencias de Node (si las hay)
- `.env` - Variables de entorno (no sobrescribe las del servidor)
- `PEDIDOS-PIZARRA/*.pdf` - PDFs existentes (no se borran)

---

## 🧪 Probar el Deploy

Una vez configurados los secrets:

```bash
# Hacer un cambio pequeño
echo "# Deploy test" >> README.md

# Commitear
git add .
git commit -m "test: Verify automatic deployment"

# Push (esto activará el deploy)
git push origin claude/review-project-demo-018atWe9Dxp8inzeTaBRPgvy
```

Luego ve a:
- **GitHub** → Tu repo → **Actions** → Ver el workflow corriendo
- **Tu sitio** → https://pizarra-ventas.socialsflow.io (debería actualizarse en ~1-2 min)

---

## 🐛 Troubleshooting

### Error: "Permission denied (publickey)"
- ✅ Verifica que `SSH_PRIVATE_KEY` esté completo (incluye BEGIN/END)
- ✅ Asegúrate de haber agregado la clave pública en Hostinger
- ✅ Verifica que `SSH_USERNAME` sea correcto

### Error: "Host key verification failed"
- ✅ El workflow maneja esto automáticamente con `ssh-keyscan`
- ✅ Si persiste, verifica `SSH_HOST` y `SSH_PORT`

### Error: "rsync: change_dir ... failed: No such file or directory"
- ✅ Verifica que `REMOTE_PATH` sea la ruta correcta
- ✅ Formato correcto: `/home/uXXXXXXXXX/domains/pizarra-ventas.socialsflow.io/public_html`

### Los cambios no se ven en el sitio
- ✅ Verifica que el workflow terminó con éxito (GitHub Actions)
- ✅ Limpia caché del navegador (Ctrl+Shift+R)
- ✅ Si usas Cloudflare, purga el caché CDN

---

## 📞 Soporte

Si necesitas ayuda, revisa:
1. Los logs del workflow en GitHub Actions
2. La documentación de Hostinger sobre SSH
3. El estado del servidor en el panel de Hostinger

---

✨ **¡Listo!** Una vez configurados los secrets, cada push se deployará automáticamente.
