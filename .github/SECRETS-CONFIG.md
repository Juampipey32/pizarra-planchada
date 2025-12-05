# 🔐 Configuración de GitHub Secrets - VALORES EXACTOS

## URL para configurar:
https://github.com/Juampipey32/pizarra-planchada/settings/secrets/actions

---

## ✅ Secrets que necesitás agregar:

### 1️⃣ SSH_HOST
```
147.93.37.161
```

---

### 2️⃣ SSH_PORT
```
65002
```

---

### 3️⃣ SSH_USERNAME
```
u363074645
```

---

### 4️⃣ REMOTE_PATH
```
/home/u363074645/domains/pizarra-ventas.socialsflow.io/public_html
```

---

### 5️⃣ SSH_PRIVATE_KEY

**IMPORTANTE:** Para este secret necesitás generar una clave SSH.

#### Opción A: Generar desde tu computadora local

1. Abrí tu terminal y ejecutá:
```bash
ssh-keygen -t ed25519 -C "deploy-pizarra" -f ~/.ssh/hostinger_deploy
```

2. Cuando pregunte por passphrase, **dejá en blanco** (solo presioná ENTER)

3. Vas a obtener 2 archivos:
   - `~/.ssh/hostinger_deploy` (clave PRIVADA)
   - `~/.ssh/hostinger_deploy.pub` (clave PÚBLICA)

4. Ver la clave PRIVADA (copiar TODO para el secret):
```bash
cat ~/.ssh/hostinger_deploy
```

5. Ver la clave PÚBLICA (para agregar en Hostinger):
```bash
cat ~/.ssh/hostinger_deploy.pub
```

6. Agregar la clave PÚBLICA en Hostinger:
   - Ve a: https://hpanel.hostinger.com
   - Avanzado → **SSH Access**
   - Scroll hasta **"SSH Keys"**
   - Click en **"Manage SSH Keys"**
   - Click en **"Add SSH Key"**
   - Pegá el contenido de `hostinger_deploy.pub`
   - Guardá

7. Probar la conexión:
```bash
ssh -i ~/.ssh/hostinger_deploy -p 65002 u363074645@147.93.37.161
```
   - Si pide confirmar fingerprint, escribí `yes`
   - Deberías entrar al servidor sin pedir contraseña

8. Copiar la clave PRIVADA completa al secret `SSH_PRIVATE_KEY` en GitHub
   - Debe incluir las líneas `-----BEGIN OPENSSH PRIVATE KEY-----` y `-----END OPENSSH PRIVATE KEY-----`

---

#### Opción B: Usar clave SSH existente

Si ya tenés una clave SSH configurada para Hostinger:

1. Encontrá tu clave privada (probablemente en `~/.ssh/id_rsa` o `~/.ssh/id_ed25519`)

2. Copiá TODO el contenido:
```bash
cat ~/.ssh/id_ed25519  # o el nombre de tu clave
```

3. Pegalo en el secret `SSH_PRIVATE_KEY` en GitHub

---

## 📋 Checklist de configuración:

- [ ] Crear secret `SSH_HOST` = `147.93.37.161`
- [ ] Crear secret `SSH_PORT` = `65002`
- [ ] Crear secret `SSH_USERNAME` = `u363074645`
- [ ] Crear secret `REMOTE_PATH` = `/home/u363074645/domains/pizarra-ventas.socialsflow.io/public_html`
- [ ] Generar clave SSH (si no tenés)
- [ ] Agregar clave pública en Hostinger
- [ ] Crear secret `SSH_PRIVATE_KEY` = [contenido completo de clave privada]
- [ ] Hacer un push de prueba
- [ ] Verificar workflow en GitHub Actions
- [ ] Verificar deploy en https://pizarra-ventas.socialsflow.io

---

## 🧪 Test rápido después de configurar:

```bash
# Commit vacío para testear
git commit --allow-empty -m "test: Verify auto-deploy"
git push

# Luego ve a:
# https://github.com/Juampipey32/pizarra-planchada/actions
```

El deploy debería completarse en 1-2 minutos ✅
