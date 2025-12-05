#!/bin/bash
# Script para generar y configurar clave SSH para deploy a Hostinger

echo "🔐 Configuración de Clave SSH para Deploy Automático"
echo "======================================================"
echo ""

# Colores
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Generar clave SSH
echo -e "${BLUE}1. Generando clave SSH...${NC}"
ssh-keygen -t ed25519 -C "deploy-pizarra-planchada" -f ~/.ssh/hostinger_deploy -N ""

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Clave SSH generada exitosamente!${NC}"
    echo ""
else
    echo "❌ Error al generar clave SSH"
    exit 1
fi

# Mostrar clave pública
echo -e "${BLUE}2. Clave PÚBLICA (agregar en Hostinger):${NC}"
echo -e "${YELLOW}════════════════════════════════════════${NC}"
cat ~/.ssh/hostinger_deploy.pub
echo -e "${YELLOW}════════════════════════════════════════${NC}"
echo ""
echo "➡️  Ve a: https://hpanel.hostinger.com"
echo "    Avanzado → SSH Access → Manage SSH Keys → Add SSH Key"
echo "    Copia y pega la clave de arriba ☝️"
echo ""
read -p "Presiona ENTER cuando hayas agregado la clave en Hostinger..."
echo ""

# Probar conexión SSH
echo -e "${BLUE}3. Probando conexión SSH...${NC}"
ssh -o StrictHostKeyChecking=no -i ~/.ssh/hostinger_deploy -p 65002 u363074645@147.93.37.161 "echo 'Conexión exitosa!' && pwd"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Conexión SSH funcionando correctamente!${NC}"
    echo ""
else
    echo "❌ Error en la conexión SSH. Verifica que hayas agregado la clave en Hostinger."
    exit 1
fi

# Mostrar clave privada para GitHub
echo -e "${BLUE}4. Clave PRIVADA (para GitHub Secret SSH_PRIVATE_KEY):${NC}"
echo -e "${YELLOW}════════════════════════════════════════${NC}"
cat ~/.ssh/hostinger_deploy
echo -e "${YELLOW}════════════════════════════════════════${NC}"
echo ""
echo -e "${GREEN}✅ ¡Todo listo!${NC}"
echo ""
echo "📋 Próximos pasos:"
echo "1. Ve a: https://github.com/Juampipey32/pizarra-planchada/settings/secrets/actions"
echo "2. Click en 'New repository secret'"
echo "3. Agrega estos 5 secrets:"
echo ""
echo "   Nombre: SSH_HOST"
echo "   Valor: 147.93.37.161"
echo ""
echo "   Nombre: SSH_PORT"
echo "   Valor: 65002"
echo ""
echo "   Nombre: SSH_USERNAME"
echo "   Valor: u363074645"
echo ""
echo "   Nombre: SSH_PRIVATE_KEY"
echo "   Valor: [La clave privada mostrada arriba ☝️ - copia TODO desde BEGIN hasta END]"
echo ""
echo "   Nombre: REMOTE_PATH"
echo "   Valor: /home/u363074645/domains/pizarra-ventas.socialsflow.io/public_html"
echo ""
echo "🚀 Después de configurar los secrets, haz un push y el deploy será automático!"
