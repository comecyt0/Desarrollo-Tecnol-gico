#!/usr/bin/env bash
# =============================================================================
# COMECYT — Script de Deployment a Producción
# Uso: bash deploy.sh
# Requiere: PHP 8.2+, Composer, Node 18+, PostgreSQL 15+
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()    { echo -e "${GREEN}[OK]${NC}  $1"; }
warn()    { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()   { echo -e "${RED}[ERR]${NC}  $1"; exit 1; }
section() { echo -e "\n${YELLOW}=== $1 ===${NC}"; }

# ── Verificar que estamos en el directorio correcto ──────────────────────────
[[ -f "artisan" ]] || error "Ejecuta este script desde apps/api/"

section "1. Verificar entorno de producción"

[[ "${APP_ENV:-}" == "production" ]] || warn "APP_ENV no es 'production'. Continúa bajo tu responsabilidad."
[[ "${APP_DEBUG:-}" == "false" ]]   || warn "APP_DEBUG no es 'false'. Asegúrate de desactivarlo en .env"
[[ -n "${APP_KEY:-}" ]]             || error "APP_KEY no está definida. Ejecuta: php artisan key:generate"
[[ -n "${JWT_SECRET:-}" ]]          || error "JWT_SECRET no está definida. Ejecuta: php artisan jwt:secret"
[[ -n "${DB_DATABASE:-}" ]]         || error "DB_DATABASE no está definida en .env"

info "Variables de entorno básicas presentes"

section "2. Instalar dependencias PHP (producción)"
composer install --no-dev --optimize-autoloader --no-interaction
info "Composer OK"

section "3. Limpiar y optimizar cachés de Laravel"
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan event:cache
info "Cachés de Laravel optimizadas"

section "4. Migraciones de base de datos"
php artisan migrate --force
info "Migraciones ejecutadas"

section "5. Seeders básicos (roles, institución COMECYT, admin)"
php artisan db:seed --class=DatabaseSeeder --force
info "Seeders ejecutados (APP_ENV=production omite UsuariosPruebaSeeder automáticamente)"

section "6. Storage symlink"
php artisan storage:link 2>/dev/null || warn "Storage link ya existe — OK"
info "Storage symlink listo"

section "7. Permisos de directorios"
chmod -R 775 storage bootstrap/cache
info "Permisos aplicados a storage/ y bootstrap/cache/"

section "8. Scheduler — configurar crontab"

CRON_CMD="* * * * * cd $(pwd) && php artisan schedule:run >> /dev/null 2>&1"
CRON_EXISTS=$(crontab -l 2>/dev/null | grep -F "schedule:run" || true)

if [[ -z "$CRON_EXISTS" ]]; then
    (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
    info "Crontab configurado: schedule:run cada minuto"
else
    warn "Crontab ya tiene schedule:run — no se modificó"
fi

section "9. JWT secret — verificar"
php artisan tinker --execute="echo app('tymon.jwt.provider.jwt') ? 'JWT OK' : 'JWT FAIL';" 2>/dev/null | grep -q "JWT OK" \
    && info "JWT configurado" \
    || warn "No se pudo verificar JWT — revisa JWT_SECRET en .env"

section "10. Health check"
php artisan about --only=Environment 2>/dev/null | grep -E "Environment|Debug|Version" || true

echo ""
echo -e "${GREEN}═══════════════════════════════════════════════${NC}"
echo -e "${GREEN}  COMECYT desplegado exitosamente en producción ${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════${NC}"
echo ""
echo "Próximos pasos manuales:"
echo "  1. Configurar SMTP en .env (MAIL_MAILER=smtp + credenciales)"
echo "  2. Apuntar el dominio al servidor (DNS)"
echo "  3. Configurar SSL con Certbot: certbot --nginx -d tudominio.mx"
echo "  4. Reiniciar PHP-FPM: sudo systemctl restart php8.2-fpm"
