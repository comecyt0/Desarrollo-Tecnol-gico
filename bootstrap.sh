#!/usr/bin/env bash
# =============================================================================
# COMECYT — Bootstrap de instalación en servidor de producción
# =============================================================================
# Uso:
#   sudo bash -c "$(curl -sSL https://raw.githubusercontent.com/comecyt0/Desarrollo-Tecnol-gico/main/bootstrap.sh)"
#
# Requisitos:
#   - Ubuntu 22.04 LTS (o Debian 12)
#   - Acceso sudo/root
#   - Dominio público apuntando al servidor (DNS configurado)
#   - Puertos 80 y 443 abiertos al internet
#   - Acceso a internet desde el servidor
#
# El script es idempotente: si falla a la mitad, lo puedes re-correr.
# =============================================================================

set -euo pipefail

# ── Colores ─────────────────────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
BOLD='\033[1m'
NC='\033[0m'

info()    { echo -e "${GREEN}[OK]${NC}  $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERR]${NC} $1"; exit 1; }
step()    { echo -e "\n${BLUE}${BOLD}═══ $1 ═══${NC}"; }
prompt()  { echo -e "${YELLOW}${BOLD}? $1${NC}"; }

# ── Verificaciones previas ──────────────────────────────────────────────────
[[ $EUID -eq 0 ]] || error "Este script requiere sudo/root: sudo bash bootstrap.sh"
command -v apt >/dev/null || error "Sistema no soportado. Requiere Ubuntu/Debian con apt."

clear
cat <<'BANNER'

╔══════════════════════════════════════════════════════════════════════════╗
║                                                                          ║
║   COMECYT — Bootstrap de Producción                                      ║
║   Sistema de Gestión de Proyectos de Desarrollo Tecnológico              ║
║                                                                          ║
║   Tiempo estimado: 30-60 minutos                                         ║
║                                                                          ║
╚══════════════════════════════════════════════════════════════════════════╝

BANNER

echo "Este script va a instalar y configurar el sistema desde cero."
echo "Antes de continuar, asegúrate de tener:"
echo ""
echo "  ✓ Dominio público apuntando a este servidor (DNS configurado)"
echo "  ✓ Puertos 80 y 443 abiertos en el firewall"
echo "  ✓ Email institucional para Let's Encrypt"
echo ""
read -rp "¿Continuar? (s/N): " continuar
[[ "${continuar,,}" == "s" || "${continuar,,}" == "si" ]] || { echo "Cancelado."; exit 0; }

# ════════════════════════════════════════════════════════════════════════════
# 1. Recolectar variables de configuración
# ════════════════════════════════════════════════════════════════════════════
step "1. Configuración inicial"

prompt "Dominio público del sistema (sin https://, ej: apoyos.comecyt.gob.mx)"
read -rp "  → " DOMINIO
[[ -n "$DOMINIO" ]] || error "El dominio es obligatorio."

prompt "Email para Let's Encrypt y avisos (ej: administracion@comecyt.gob.mx)"
read -rp "  → " ADMIN_EMAIL
[[ -n "$ADMIN_EMAIL" ]] || error "El email es obligatorio."

prompt "Nombre del repo de GitHub a clonar"
echo "  Default: https://github.com/comecyt0/Desarrollo-Tecnol-gico.git"
read -rp "  → (Enter para default) " REPO_URL
REPO_URL="${REPO_URL:-https://github.com/comecyt0/Desarrollo-Tecnol-gico.git}"

prompt "Directorio donde instalar el sistema"
echo "  Default: /var/www/comecyt"
read -rp "  → (Enter para default) " INSTALL_DIR
INSTALL_DIR="${INSTALL_DIR:-/var/www/comecyt}"

# Verificar versión de Ubuntu/Debian
. /etc/os-release
info "Sistema detectado: ${NAME} ${VERSION_ID}"

# Resumen
echo ""
echo "─────────────────────────────────────────────"
echo "  Dominio:       https://${DOMINIO}"
echo "  Email:         ${ADMIN_EMAIL}"
echo "  Repo:          ${REPO_URL}"
echo "  Directorio:    ${INSTALL_DIR}"
echo "─────────────────────────────────────────────"
echo ""
read -rp "¿Es correcto? (s/N): " confirmar
[[ "${confirmar,,}" == "s" || "${confirmar,,}" == "si" ]] || { echo "Cancelado."; exit 0; }

# ════════════════════════════════════════════════════════════════════════════
# 2. Instalar dependencias del sistema
# ════════════════════════════════════════════════════════════════════════════
step "2. Instalando paquetes base (puede tardar 5-10 min)"

apt update -qq

DEBIAN_FRONTEND=noninteractive apt install -y -qq \
    software-properties-common ca-certificates curl gnupg lsb-release \
    git nginx supervisor certbot python3-certbot-nginx \
    postgresql postgresql-contrib \
    unzip zip \
    >/dev/null

# PHP 8.2 (en Ubuntu 22.04 viene por default; en otros casos requiere PPA)
if ! command -v php8.2 >/dev/null && ! php -v 2>/dev/null | grep -q "^PHP 8.2"; then
    info "Instalando PHP 8.2 desde PPA ondrej/php..."
    add-apt-repository -y ppa:ondrej/php >/dev/null 2>&1 || true
    apt update -qq
fi

DEBIAN_FRONTEND=noninteractive apt install -y -qq \
    php8.2-cli php8.2-fpm php8.2-common \
    php8.2-pgsql php8.2-mbstring php8.2-xml php8.2-curl \
    php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl \
    php8.2-sodium php8.2-readline php8.2-tokenizer \
    >/dev/null

# Composer
if ! command -v composer >/dev/null; then
    info "Instalando Composer..."
    curl -sS https://getcomposer.org/installer | php -- --quiet --install-dir=/usr/local/bin --filename=composer >/dev/null
fi

# Node.js 20 LTS
if ! command -v node >/dev/null || ! node -v | grep -qE "^v20"; then
    info "Instalando Node.js 20 LTS..."
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash - >/dev/null 2>&1
    apt install -y -qq nodejs >/dev/null
fi

info "PHP $(php -v | head -1 | cut -d' ' -f2)"
info "Composer $(composer --version 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1)"
info "Node $(node -v)"
info "Postgres $(psql --version | grep -oE '[0-9]+\.[0-9]+' | head -1)"
info "nginx $(nginx -v 2>&1 | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')"

# ════════════════════════════════════════════════════════════════════════════
# 3. Configurar firewall
# ════════════════════════════════════════════════════════════════════════════
step "3. Configurando firewall (UFW)"

if command -v ufw >/dev/null; then
    ufw --force enable >/dev/null 2>&1 || true
    ufw allow OpenSSH >/dev/null 2>&1 || true
    ufw allow 80/tcp >/dev/null 2>&1 || true
    ufw allow 443/tcp >/dev/null 2>&1 || true
    info "UFW: puertos 22, 80, 443 abiertos. Resto cerrado."
else
    warn "UFW no instalado. Asegúrate de tener firewall configurado manualmente."
fi

# ════════════════════════════════════════════════════════════════════════════
# 4. Generar secretos
# ════════════════════════════════════════════════════════════════════════════
step "4. Generando secretos criptográficamente fuertes"

DB_PASS=$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)
HEALTH_TOKEN=$(openssl rand -hex 32)
REVERB_KEY=$(openssl rand -hex 16)
REVERB_SECRET=$(openssl rand -hex 32)

# Guardar en archivo seguro para referencia
SECRETS_FILE="/root/.comecyt-secrets-$(date +%Y%m%d).txt"
cat > "${SECRETS_FILE}" <<EOF
COMECYT — Secretos generados el $(date)
═══════════════════════════════════════════════════════════════
⚠ Guarda este archivo en lugar seguro (vault institucional).
⚠ Borra de este servidor cuando lo hayas respaldado.

DB_PASSWORD=${DB_PASS}
HEALTH_TOKEN=${HEALTH_TOKEN}
REVERB_APP_KEY=${REVERB_KEY}
REVERB_APP_SECRET=${REVERB_SECRET}
EOF
chmod 600 "${SECRETS_FILE}"
chown root:root "${SECRETS_FILE}"

info "Secretos guardados en: ${SECRETS_FILE} (chmod 600)"

# ════════════════════════════════════════════════════════════════════════════
# 5. Crear base de datos PostgreSQL
# ════════════════════════════════════════════════════════════════════════════
step "5. Creando base de datos PostgreSQL"

systemctl start postgresql
systemctl enable postgresql >/dev/null

# Crear usuario y BD (idempotente)
sudo -u postgres psql <<SQL >/dev/null 2>&1 || true
CREATE USER comecyt_user WITH PASSWORD '${DB_PASS}';
CREATE DATABASE comecyt_prod OWNER comecyt_user ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE comecyt_prod TO comecyt_user;
SQL

# Si el usuario ya existía, actualizar password
sudo -u postgres psql -c "ALTER USER comecyt_user WITH PASSWORD '${DB_PASS}';" >/dev/null

info "Base de datos comecyt_prod creada con usuario comecyt_user."

# ════════════════════════════════════════════════════════════════════════════
# 6. Clonar repositorio
# ════════════════════════════════════════════════════════════════════════════
step "6. Clonando repositorio"

if [[ -d "${INSTALL_DIR}/.git" ]]; then
    warn "Directorio ${INSTALL_DIR} ya existe. Haciendo git pull..."
    cd "${INSTALL_DIR}"
    git config --global --add safe.directory "${INSTALL_DIR}"
    sudo -u www-data git pull origin main || git pull origin main
else
    mkdir -p "$(dirname "${INSTALL_DIR}")"
    git clone "${REPO_URL}" "${INSTALL_DIR}"
fi

# Ownership a www-data
chown -R www-data:www-data "${INSTALL_DIR}"

info "Repositorio clonado en ${INSTALL_DIR}"

# ════════════════════════════════════════════════════════════════════════════
# 7. Backend: configuración .env + dependencias + deploy.sh
# ════════════════════════════════════════════════════════════════════════════
step "7. Configurando backend Laravel"

cd "${INSTALL_DIR}/apps/api"

# Copiar .env.example y sustituir valores
if [[ ! -f .env ]]; then
    cp .env.example .env
fi

# Función para setear variable en .env
set_env() {
    local key="$1"
    local value="$2"
    if grep -qE "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

set_env "APP_NAME" "COMECYT"
set_env "APP_ENV" "production"
set_env "APP_DEBUG" "false"
set_env "APP_URL" "https://${DOMINIO}"
set_env "APP_LOCALE" "es"
set_env "NEXT_PUBLIC_APP_URL" "https://${DOMINIO}"
set_env "DB_CONNECTION" "pgsql"
set_env "DB_HOST" "127.0.0.1"
set_env "DB_PORT" "5432"
set_env "DB_DATABASE" "comecyt_prod"
set_env "DB_USERNAME" "comecyt_user"
set_env "DB_PASSWORD" "${DB_PASS}"
set_env "HASH_DRIVER" "argon2id"
set_env "JWT_TTL" "60"
set_env "COOKIE_DOMAIN" ".${DOMINIO}"
set_env "COOKIE_SECURE" "true"
set_env "COOKIE_SAME_SITE" "Strict"
set_env "CORS_ALLOWED_ORIGINS" "https://${DOMINIO}"
set_env "TRUSTED_PROXIES" "127.0.0.1"
set_env "MAIL_MAILER" "log"
set_env "MAIL_FROM_ADDRESS" "noreply@${DOMINIO}"
set_env "MAIL_FROM_NAME" "COMECYT"
set_env "BROADCAST_CONNECTION" "reverb"
set_env "REVERB_APP_ID" "comecyt"
set_env "REVERB_APP_KEY" "${REVERB_KEY}"
set_env "REVERB_APP_SECRET" "${REVERB_SECRET}"
set_env "REVERB_HOST" "127.0.0.1"
set_env "REVERB_PORT" "8080"
set_env "REVERB_SCHEME" "http"
set_env "NEXT_PUBLIC_REVERB_APP_KEY" "${REVERB_KEY}"
set_env "NEXT_PUBLIC_REVERB_HOST" "${DOMINIO}"
set_env "NEXT_PUBLIC_REVERB_PORT" "443"
set_env "NEXT_PUBLIC_REVERB_SCHEME" "https"
set_env "HEALTH_TOKEN" "${HEALTH_TOKEN}"
set_env "FILESYSTEM_DISK" "local"
set_env "QUEUE_CONNECTION" "database"
set_env "CACHE_STORE" "database"
set_env "SESSION_DRIVER" "database"
set_env "LOG_CHANNEL" "stack"
set_env "LOG_STACK" "daily"
set_env "LOG_LEVEL" "warning"

chmod 600 .env
chown www-data:www-data .env

info ".env configurado con permisos 600"

# Composer install
info "Instalando dependencias Composer (sin dev, optimizadas)..."
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction --quiet

# Generar APP_KEY y JWT_SECRET
sudo -u www-data php artisan key:generate --force >/dev/null
sudo -u www-data php artisan jwt:secret --force >/dev/null
info "APP_KEY y JWT_SECRET generados"

# Storage symlink
sudo -u www-data php artisan storage:link >/dev/null 2>&1 || true

# Migraciones + seeders
info "Corriendo migraciones..."
sudo -u www-data php artisan migrate --force --no-interaction
info "Corriendo seeders básicos (roles, categorías, admin)..."
sudo -u www-data php artisan db:seed --force --no-interaction

# Correr deploy.sh (cachea config, valida, configura crontab)
info "Ejecutando deploy.sh..."
APP_ENV=production APP_DEBUG=false APP_KEY="$(grep '^APP_KEY=' .env | cut -d= -f2-)" \
    JWT_SECRET="$(grep '^JWT_SECRET=' .env | cut -d= -f2-)" \
    DB_DATABASE=comecyt_prod \
    CORS_ALLOWED_ORIGINS="https://${DOMINIO}" \
    bash deploy.sh 2>&1 | tail -20 || warn "deploy.sh tuvo warnings (revisa el output)"

# ════════════════════════════════════════════════════════════════════════════
# 8. Frontend: build de producción
# ════════════════════════════════════════════════════════════════════════════
step "8. Construyendo frontend Next.js"

cd "${INSTALL_DIR}/apps/web"

# .env.local
cat > .env.local <<EOF
NEXT_PUBLIC_API_URL=https://${DOMINIO}/api
NEXT_PUBLIC_APP_URL=https://${DOMINIO}
NEXT_PUBLIC_REVERB_APP_KEY=${REVERB_KEY}
NEXT_PUBLIC_REVERB_HOST=${DOMINIO}
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https
NEXT_PUBLIC_INSTITUTION_NAME=COMECYT
NEXT_PUBLIC_INSTITUTION_FULL_NAME="Consejo Mexiquense de Ciencia y Tecnología"
NEXT_PUBLIC_INSTITUTION_SYSTEM_TAGLINE="Gestión de Proyectos de Desarrollo Tecnológico y Vinculación"
NEXT_PUBLIC_INSTITUTION_EMAIL=${ADMIN_EMAIL}
NEXT_PUBLIC_INSTITUTION_LOCALE=es-MX
NEXT_PUBLIC_INSTITUTION_CURRENCY=MXN
EOF
chown www-data:www-data .env.local

info "Instalando dependencias npm (puede tardar 3-5 min)..."
sudo -u www-data npm ci --legacy-peer-deps --no-audit --no-fund --silent

info "Compilando build de producción..."
sudo -u www-data npm run build 2>&1 | tail -5 || \
    sudo -u www-data npx next build --webpack 2>&1 | tail -5

info "Build completado"

# ════════════════════════════════════════════════════════════════════════════
# 9. nginx
# ════════════════════════════════════════════════════════════════════════════
step "9. Configurando nginx"

# Copiar template
cp "${INSTALL_DIR}/docs/deploy-templates/nginx-comecyt.conf" /etc/nginx/sites-available/comecyt

# Reemplazar dominio en la config
sed -i "s|tudominio.gob.mx|${DOMINIO}|g" /etc/nginx/sites-available/comecyt
sed -i "s|/var/www/comecyt|${INSTALL_DIR}|g" /etc/nginx/sites-available/comecyt

# Activar sitio
ln -sf /etc/nginx/sites-available/comecyt /etc/nginx/sites-enabled/comecyt
rm -f /etc/nginx/sites-enabled/default

# Verificar syntax (todavía sin SSL, va a fallar por ssl_certificate, lo manejamos)
sed -i 's|^.*ssl_certificate|    # ssl_certificate|g' /etc/nginx/sites-available/comecyt
sed -i 's|^.*ssl_certificate_key|    # ssl_certificate_key|g' /etc/nginx/sites-available/comecyt

# Test config
nginx -t || error "nginx config inválido. Revisa /etc/nginx/sites-available/comecyt"

systemctl reload nginx
info "nginx configurado y recargado"

# ════════════════════════════════════════════════════════════════════════════
# 10. SSL con Let's Encrypt
# ════════════════════════════════════════════════════════════════════════════
step "10. Generando certificado SSL con Let's Encrypt"

# Intentar obtener cert (puede fallar si DNS no apunta aquí)
if certbot --nginx -d "${DOMINIO}" --email "${ADMIN_EMAIL}" \
    --agree-tos --no-eff-email --redirect --non-interactive 2>&1 | tail -5; then
    info "SSL configurado correctamente"
else
    warn "Certbot falló — probablemente el DNS aún no apunta a este servidor."
    warn "Cuando el DNS esté listo, corre manualmente:"
    warn "  sudo certbot --nginx -d ${DOMINIO} --email ${ADMIN_EMAIL} --agree-tos --redirect"
fi

# ════════════════════════════════════════════════════════════════════════════
# 11. Supervisor (Reverb + Next.js + Queue workers)
# ════════════════════════════════════════════════════════════════════════════
step "11. Configurando Supervisor"

# Copiar configs y ajustar paths
for SVC in reverb next queue; do
    CONF="/etc/supervisor/conf.d/comecyt-${SVC}.conf"
    cp "${INSTALL_DIR}/docs/deploy-templates/supervisor-comecyt-${SVC}.conf" "${CONF}"
    sed -i "s|/var/www/comecyt|${INSTALL_DIR}|g" "${CONF}"
done

supervisorctl reread >/dev/null
supervisorctl update >/dev/null
sleep 3

# Verificar estado
echo ""
supervisorctl status | grep comecyt- || warn "Supervisor no muestra servicios comecyt activos."

# ════════════════════════════════════════════════════════════════════════════
# 12. Permisos finales
# ════════════════════════════════════════════════════════════════════════════
step "12. Aplicando permisos finales"

chown -R www-data:www-data "${INSTALL_DIR}"
find "${INSTALL_DIR}/apps/api/storage" -type d -exec chmod 755 {} \;
chmod -R 775 "${INSTALL_DIR}/apps/api/storage/framework" \
             "${INSTALL_DIR}/apps/api/storage/logs"
chmod -R 755 "${INSTALL_DIR}/apps/api/bootstrap/cache"
chmod 600 "${INSTALL_DIR}/apps/api/.env"

info "Permisos aplicados (755 base + 775 selectivo)"

# ════════════════════════════════════════════════════════════════════════════
# 13. Reiniciar servicios
# ════════════════════════════════════════════════════════════════════════════
step "13. Reiniciando servicios"

systemctl restart php8.2-fpm
systemctl reload nginx
supervisorctl restart comecyt-reverb 2>/dev/null || true
supervisorctl restart comecyt-next 2>/dev/null || true
supervisorctl restart 'comecyt-queue:*' 2>/dev/null || true

info "Servicios reiniciados"

# ════════════════════════════════════════════════════════════════════════════
# 14. Verificación final
# ════════════════════════════════════════════════════════════════════════════
step "14. Verificación final"

cd "${INSTALL_DIR}/apps/api"

echo ""
echo "─── DeployCheck ───────────────────────────────────"
sudo -u www-data php artisan app:deploy-check || warn "DeployCheck reportó errores. Revísalos arriba."

echo ""
echo "─── Health endpoint ───────────────────────────────"
sleep 3
HEALTH_RESPONSE=$(curl -sk -H "X-Health-Token: ${HEALTH_TOKEN}" \
    "https://${DOMINIO}/api/health" 2>/dev/null || \
    curl -s -H "X-Health-Token: ${HEALTH_TOKEN}" \
    "http://127.0.0.1/api/health" 2>/dev/null || \
    echo '{"error":"no response"}')

echo "${HEALTH_RESPONSE}"

# ════════════════════════════════════════════════════════════════════════════
# 15. Resumen final
# ════════════════════════════════════════════════════════════════════════════
clear
cat <<EOF

╔══════════════════════════════════════════════════════════════════════════╗
║                                                                          ║
║   ✅  COMECYT INSTALADO EN PRODUCCIÓN                                    ║
║                                                                          ║
╚══════════════════════════════════════════════════════════════════════════╝

📍 URL del sistema:      https://${DOMINIO}
🔑 Secretos guardados:   ${SECRETS_FILE}
📂 Directorio:           ${INSTALL_DIR}
📋 Logs Laravel:         ${INSTALL_DIR}/apps/api/storage/logs/
📋 Logs nginx:           /var/log/nginx/comecyt-*.log
📋 Logs supervisor:      /var/log/comecyt-*.log

═══ Próximos pasos manuales ════════════════════════════════════════════════

1. 🔑 Respalda el archivo de secretos en un vault institucional seguro:
   scp root@${HOSTNAME:-server}:${SECRETS_FILE} ~/comecyt-secrets-backup.txt
   Después: sudo rm ${SECRETS_FILE}

2. 📧 Configura SMTP institucional en .env:
   sudo nano ${INSTALL_DIR}/apps/api/.env
   # Editar: MAIL_MAILER=smtp + credenciales
   cd ${INSTALL_DIR}/apps/api
   sudo -u www-data php artisan config:cache
   sudo systemctl restart php8.2-fpm

3. 🔐 Login admin (cambiar password en primer login):
   https://${DOMINIO}/login
   Email/password del seeder DatabaseSeeder
   ⚠ CAMBIA LA CONTRASEÑA INMEDIATAMENTE

4. 📊 Configura monitoreo externo (UptimeRobot):
   URL:    https://${DOMINIO}/api/health
   Header: X-Health-Token: ${HEALTH_TOKEN}
   Cada:   5 minutos

5. 💾 Configura backups diarios automatizados:
   Ver: ${INSTALL_DIR}/docs/OPERATIONS.md §6.3

6. 📅 Verifica scheduler activo:
   sudo crontab -u www-data -l | grep schedule:run
   (Debe mostrar una línea — el deploy.sh ya lo configuró)

═══ Comandos útiles ════════════════════════════════════════════════════════

  Ver estado servicios:    sudo supervisorctl status
  Logs Reverb (vivo):      sudo tail -f /var/log/comecyt-reverb.log
  Logs Next (vivo):        sudo tail -f /var/log/comecyt-next.log
  Logs Laravel (vivo):     sudo tail -f ${INSTALL_DIR}/apps/api/storage/logs/laravel.log
  Re-correr DeployCheck:   cd ${INSTALL_DIR}/apps/api && sudo -u www-data php artisan app:deploy-check
  Restart todo:            sudo systemctl restart php8.2-fpm && sudo systemctl reload nginx && sudo supervisorctl restart all

═══ Documentación ══════════════════════════════════════════════════════════

  📘 Manual operativo:  ${INSTALL_DIR}/docs/OPERATIONS.md
  📕 Runbook IR:        ${INSTALL_DIR}/docs/security/incident-response.md
  📗 Guía usuarios:     ${INSTALL_DIR}/docs/USER_GUIDE.md

EOF
