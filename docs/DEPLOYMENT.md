# 🚀 Guía de Despliegue en Producción — COMECYT

> **Sistema:** Gestión de Proyectos de Desarrollo Tecnológico y Vinculación
> **Versión:** 8.x (post-auditoría de seguridad)
> **Audiencia:** Equipo de TIC del Estado de México / administradores de sistema
> **Tiempo estimado:** 60–90 minutos desde servidor limpio
> **Última revisión:** 2026-06-12

---

## 0. TL;DR — Resumen ejecutivo

```bash
# En el servidor (Ubuntu/Debian, sudo)
sudo apt update && sudo apt install -y nginx postgresql-18 php8.2-fpm \
    php8.2-{cli,common,pgsql,mbstring,xml,curl,zip,bcmath,gd,intl,sodium} \
    composer git nodejs npm certbot python3-certbot-nginx supervisor

# Clone
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/comecyt0/Desarrollo-Tecnol-gico.git comecyt
sudo chown -R www-data:www-data comecyt

# Backend
cd /var/www/comecyt/apps/api
sudo -u www-data cp .env.example .env
sudo nano .env                          # editar (sección §4)
sudo chmod 600 .env
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan jwt:secret --force
sudo bash deploy.sh                     # script idempotente

# Frontend
cd /var/www/comecyt/apps/web
sudo -u www-data cp .env.example .env.local
sudo nano .env.local                    # editar (sección §5)
sudo -u www-data npm ci --legacy-peer-deps
sudo -u www-data npm run build

# nginx + SSL + supervisord
sudo cp /var/www/comecyt/docs/deploy-templates/nginx-comecyt.conf /etc/nginx/sites-available/comecyt
sudo ln -s /etc/nginx/sites-available/comecyt /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d dominio.gob.mx

# Validar
cd /var/www/comecyt/apps/api && sudo -u www-data php artisan app:deploy-check
curl -H "X-Health-Token: $(grep HEALTH_TOKEN .env | cut -d= -f2)" https://dominio.gob.mx/api/health
```

Si los 2 últimos comandos pasan en verde → **estás en producción**.

---

## 1. Arquitectura

```
                    HTTPS (443)
                        │
                        ▼
           ┌─────────────────────────┐
           │    nginx (reverse-proxy) │  ← TLS termination + Let's Encrypt
           └─────────────────────────┘
              │ /api/*           │ /*
              ▼                  ▼
        ┌──────────┐       ┌─────────────┐
        │ php-fpm  │       │  Node.js    │
        │ Laravel  │       │  Next.js 16 │
        │ :9000    │       │  :3000      │
        └────┬─────┘       └─────────────┘
             │
             ▼
       ┌────────────┐
       │ PostgreSQL │
       │  18  :5432 │
       └────────────┘
             │
       ┌─────┴──────┐
       │ Reverb     │  ← WebSockets (notificaciones)
       │ :8080      │
       └────────────┘
             │
       ┌─────┴──────┐
       │ Supervisor │  ← Mantiene reverb + scheduler vivos
       └────────────┘
```

---

## 2. Requisitos del servidor

### Hardware mínimo (escritorio remoto / VM)

| Recurso | Mínimo | Recomendado |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| RAM | 4 GB | 8 GB |
| Disco | 40 GB SSD | 80 GB SSD |
| Ancho de banda | 100 Mbps | 1 Gbps |

### Sistema operativo

- **Ubuntu Server 22.04 LTS** (recomendado) o
- **Debian 12** o
- **Windows Server 2022** con WSL2 Ubuntu (si es escritorio remoto Microsoft)

### Software base

| Paquete | Versión | Por qué |
|---|---|---|
| PHP | **8.2+** (8.4 recomendado) | Laravel 11, Argon2id requiere sodium |
| PostgreSQL | **15+** (18 recomendado) | DB de producción |
| Node.js | **20 LTS** | Next.js 16 |
| nginx | **1.24+** | Reverse-proxy + TLS |
| Composer | 2.x | Dependencias PHP |
| npm | 10.x | Dependencias Node |
| Certbot | Última | SSL Let's Encrypt |
| Supervisor | 4.x | Workers persistentes |

### Extensiones PHP obligatorias

```bash
sudo apt install -y \
    php8.2-cli php8.2-common php8.2-fpm \
    php8.2-pgsql \
    php8.2-mbstring php8.2-xml php8.2-curl \
    php8.2-zip php8.2-bcmath php8.2-gd \
    php8.2-intl php8.2-sodium php8.2-readline \
    php8.2-tokenizer php8.2-fileinfo
```

`php8.2-sodium` es **crítico** para Argon2id (hashing de contraseñas).

---

## 3. Preparación del servidor (one-time)

### 3.1 Usuario y permisos

```bash
# Asegurar que existe www-data (suele estar por default)
id www-data || sudo useradd -r -s /usr/sbin/nologin www-data

# Crear directorio raíz
sudo mkdir -p /var/www
sudo chown www-data:www-data /var/www
```

### 3.2 PostgreSQL — Base de datos y usuario

```bash
sudo -u postgres psql <<'SQL'
-- Generar password fuerte ANTES y reemplazar:
CREATE USER comecyt_user WITH PASSWORD 'REEMPLAZAR_CON_OPENSSL_RAND';
CREATE DATABASE comecyt_prod OWNER comecyt_user ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE comecyt_prod TO comecyt_user;
ALTER USER comecyt_user CREATEDB;  -- para migrations:fresh en dev/staging
SQL
```

Generar el password antes:
```bash
openssl rand -base64 32
```

### 3.3 Firewall (UFW)

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp     # nginx HTTP (redirect a HTTPS)
sudo ufw allow 443/tcp    # nginx HTTPS
sudo ufw --force enable
```

⚠️ **NO** abrir puertos 5432 (Postgres), 6379 (Redis), 9000 (php-fpm), 3000 (Next), 8080 (Reverb) al exterior. Solo accesibles via loopback.

---

## 4. Clone y configuración del Backend

### 4.1 Clone del repositorio

```bash
cd /var/www
sudo git clone https://github.com/comecyt0/Desarrollo-Tecnol-gico.git comecyt
sudo chown -R www-data:www-data comecyt
cd comecyt
```

### 4.2 Composer install (producción)

```bash
cd /var/www/comecyt/apps/api
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
```

### 4.3 Configurar `.env` del backend

```bash
sudo -u www-data cp .env.example .env
sudo nano .env
```

**Variables CRÍTICAS a setear**:

```env
# ─── App ──────────────────────────────────────────────────
APP_NAME="COMECYT — Gestión de Proyectos"
APP_ENV=production                           # ← obligatorio
APP_DEBUG=false                              # ← obligatorio (DeployCheck falla si true)
APP_URL=https://tudominio.gob.mx
APP_LOCALE=es
NEXT_PUBLIC_APP_URL=https://tudominio.gob.mx

# ─── Database ─────────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=comecyt_prod
DB_USERNAME=comecyt_user
DB_PASSWORD=         # ← pegar el password generado con openssl rand -base64 32

# ─── Hashing (Argon2id — defense-in-depth) ────────────────
HASH_DRIVER=argon2id
ARGON_MEMORY=65536
ARGON_TIME=4
ARGON_THREADS=1
BCRYPT_ROUNDS=12

# ─── JWT (cookie HttpOnly comecyt_auth) ───────────────────
JWT_TTL=60                  # 1 hora
JWT_REFRESH_TTL=20160       # 14 días
COOKIE_DOMAIN=.tudominio.gob.mx
COOKIE_SECURE=true
COOKIE_SAME_SITE=Strict

# ─── CORS estricto en producción ──────────────────────────
CORS_ALLOWED_ORIGINS=https://tudominio.gob.mx

# ─── Rate limiting (default robusto) ──────────────────────
RATE_LIMIT_MAX=300
RATE_LIMIT_WINDOW=60
AUTH_LOGIN_RATE_LIMIT=5
AUTH_LOGIN_RATE_WINDOW=60
AUTH_ACCOUNT_LOCKOUT=10
AUTH_LOCKOUT_MINUTES=15
API_GATEWAY_MAX_IPS=1000
API_GATEWAY_LOCKDOWN_MINUTES=5

# ─── Trusted proxies (nginx loopback) ─────────────────────
TRUSTED_PROXIES=127.0.0.1

# ─── Mail SMTP (institucional) ────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp.comecyt.gob.mx          # ← coordinar con TIC
MAIL_PORT=587
MAIL_USERNAME=noreply@comecyt.gob.mx
MAIL_PASSWORD=        # ← password SMTP institucional
MAIL_FROM_ADDRESS=noreply@comecyt.gob.mx
MAIL_FROM_NAME="COMECYT"
MAIL_ENCRYPTION=tls

# ─── Reverb WebSockets ────────────────────────────────────
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=comecyt
REVERB_APP_KEY=    # openssl rand -hex 16
REVERB_APP_SECRET= # openssl rand -hex 32
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
NEXT_PUBLIC_REVERB_APP_KEY="${REVERB_APP_KEY}"
NEXT_PUBLIC_REVERB_HOST=tudominio.gob.mx
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https

# ─── Web Push (VAPID) ─────────────────────────────────────
# Generar con: php artisan tinker → Minishlink\WebPush\VAPID::createVapidKeys()
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
VAPID_SUBJECT=mailto:soporte@comecyt.gob.mx

# ─── Health check (monitoreo externo) ─────────────────────
HEALTH_TOKEN=    # openssl rand -hex 32

# ─── Storage / Cache ──────────────────────────────────────
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_LIFETIME=120

# ─── Logging ──────────────────────────────────────────────
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning           # ← warning en prod, no debug
LOG_DAILY_DAYS=30
LOG_SECURITY_LEVEL=warning
LOG_SECURITY_DAYS=90        # 3 meses para forensics
```

### 4.4 Permisos restrictivos del `.env`

```bash
sudo chmod 600 /var/www/comecyt/apps/api/.env
sudo chown www-data:www-data /var/www/comecyt/apps/api/.env
```

### 4.5 Generar secretos

```bash
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan key:generate --force      # APP_KEY
sudo -u www-data php artisan jwt:secret --force        # JWT_SECRET
```

### 4.6 Migraciones y seeders

```bash
sudo -u www-data php artisan migrate --force           # crea todas las tablas
sudo -u www-data php artisan db:seed --force           # roles, COMECYT empresa, admin
```

> El admin por default se crea con la contraseña en `DatabaseSeeder` — **cambia esa contraseña al primer login**.
> `UsuariosPruebaSeeder` se omite automáticamente en `APP_ENV=production`.

### 4.7 Ejecutar el script de deploy

```bash
cd /var/www/comecyt/apps/api
sudo bash deploy.sh
```

El script:
- Valida APP_ENV, APP_DEBUG, claves
- Detecta passwords débiles y aborta con error
- Cachea config, routes, views, events
- Aplica permisos 755 + 775 selectivo en storage/
- Configura crontab del scheduler

### 4.8 Verificar

```bash
sudo -u www-data php artisan app:deploy-check
```

Debe mostrar `0 errores`. Si hay errores, NO continúes — léelos y corrige.

---

## 5. Configuración del Frontend

### 5.1 Configurar `.env.local`

```bash
cd /var/www/comecyt/apps/web
sudo -u www-data cp .env.example .env.local
sudo nano .env.local
```

```env
NEXT_PUBLIC_API_URL=https://tudominio.gob.mx/api
NEXT_PUBLIC_APP_URL=https://tudominio.gob.mx
NEXT_PUBLIC_REVERB_APP_KEY=<mismo que en backend>
NEXT_PUBLIC_REVERB_HOST=tudominio.gob.mx
NEXT_PUBLIC_REVERB_PORT=443
NEXT_PUBLIC_REVERB_SCHEME=https

# Institucional
NEXT_PUBLIC_INSTITUTION_NAME=COMECYT
NEXT_PUBLIC_INSTITUTION_FULL_NAME="Consejo Mexiquense de Ciencia y Tecnología"
NEXT_PUBLIC_INSTITUTION_SYSTEM_TAGLINE="Gestión de Proyectos de Desarrollo Tecnológico y Vinculación"
NEXT_PUBLIC_INSTITUTION_EMAIL=administracion@comecyt.gob.mx
NEXT_PUBLIC_INSTITUTION_LOCALE=es-MX
NEXT_PUBLIC_INSTITUTION_CURRENCY=MXN

# Sentry (opcional — deja vacío si no hay)
NEXT_PUBLIC_SENTRY_DSN=
```

### 5.2 Build

```bash
cd /var/www/comecyt/apps/web
sudo -u www-data npm ci --legacy-peer-deps
sudo -u www-data npm run build
```

> Si el build falla con error de Turbopack en linux x64, usa el fallback:
> `sudo -u www-data npx next build --webpack`

### 5.3 Probar el frontend manualmente

```bash
sudo -u www-data PORT=3000 npm start &
sleep 5
curl -I http://127.0.0.1:3000     # debe responder 200/OK
kill %1
```

---

## 6. nginx + SSL

### 6.1 Archivo de configuración

Crea `/etc/nginx/sites-available/comecyt`:

```nginx
# Redirect HTTP → HTTPS
server {
    listen 80;
    server_name tudominio.gob.mx www.tudominio.gob.mx;
    return 301 https://$host$request_uri;
}

# Main HTTPS server
server {
    listen 443 ssl http2;
    server_name tudominio.gob.mx www.tudominio.gob.mx;

    # SSL — Certbot completará estos:
    # ssl_certificate /etc/letsencrypt/live/tudominio.gob.mx/fullchain.pem;
    # ssl_certificate_key /etc/letsencrypt/live/tudominio.gob.mx/privkey.pem;

    # Security headers globales (CSP lo setea Next.js)
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    # Tamaño máximo de upload (PDFs de hasta 5MB + buffer)
    client_max_body_size 8M;

    # Logs
    access_log /var/log/nginx/comecyt-access.log;
    error_log  /var/log/nginx/comecyt-error.log warn;

    # ─── API Laravel (php-fpm) ──────────────────────────────
    location ^~ /api/ {
        try_files $uri /var/www/comecyt/apps/api/public/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/comecyt/apps/api/public$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT /var/www/comecyt/apps/api/public;

        # Pass headers para JWT cookie + CORS
        fastcgi_read_timeout 60s;
    }

    # ─── Storage público (uploads documentos, imágenes carrusel) ─
    location /storage/ {
        alias /var/www/comecyt/apps/api/storage/app/public/;
        expires 7d;
        add_header Cache-Control "public, immutable";
    }

    # ─── WebSocket Reverb ───────────────────────────────────
    location /app/ {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
    }

    # ─── Next.js (todo lo demás) ────────────────────────────
    location / {
        proxy_pass http://127.0.0.1:3000;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_cache_bypass $http_upgrade;
    }

    # ─── Block oculto: ocultar .env si alguien intenta ──────
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }
}
```

### 6.2 Activar el sitio

```bash
sudo ln -s /etc/nginx/sites-available/comecyt /etc/nginx/sites-enabled/
sudo rm /etc/nginx/sites-enabled/default
sudo nginx -t          # debe decir "syntax is ok" y "test is successful"
sudo systemctl reload nginx
```

### 6.3 SSL con Let's Encrypt

```bash
sudo certbot --nginx -d tudominio.gob.mx -d www.tudominio.gob.mx \
    --email administracion@comecyt.gob.mx \
    --agree-tos --no-eff-email --redirect
```

Certbot edita el nginx config automáticamente, agrega los `ssl_certificate` paths, y configura renovación automática.

Verifica:
```bash
sudo certbot certificates              # lista certs activos
sudo systemctl status certbot.timer    # confirma renovación auto cada 12h
```

---

## 7. Servicios persistentes (Supervisor)

Los procesos que NO son web (Reverb WebSocket, scheduler, queue worker) requieren un supervisor para mantenerlos vivos.

### 7.1 Reverb (WebSockets)

`/etc/supervisor/conf.d/comecyt-reverb.conf`:

```ini
[program:comecyt-reverb]
command=/usr/bin/php /var/www/comecyt/apps/api/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/comecyt/apps/api
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
stdout_logfile=/var/log/comecyt-reverb.log
stderr_logfile=/var/log/comecyt-reverb-err.log
environment=APP_ENV="production"
```

### 7.2 Next.js (frontend) — opcional si no usas pm2

`/etc/supervisor/conf.d/comecyt-next.conf`:

```ini
[program:comecyt-next]
command=/usr/bin/npm run start
directory=/var/www/comecyt/apps/web
user=www-data
autostart=true
autorestart=true
environment=NODE_ENV="production",PORT="3000"
stdout_logfile=/var/log/comecyt-next.log
stderr_logfile=/var/log/comecyt-next-err.log
```

### 7.3 Queue worker (procesamiento asíncrono de emails, notificaciones)

`/etc/supervisor/conf.d/comecyt-queue.conf`:

```ini
[program:comecyt-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/comecyt/apps/api/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/comecyt/apps/api
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=3600
stdout_logfile=/var/log/comecyt-queue.log
```

### 7.4 Activar

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status

# Debe mostrar:
# comecyt-reverb        RUNNING
# comecyt-next          RUNNING
# comecyt-queue:_00     RUNNING
# comecyt-queue:_01     RUNNING
```

### 7.5 Scheduler (crontab)

`deploy.sh` ya lo configura, pero verifica:

```bash
sudo crontab -u www-data -l | grep schedule
# Debe mostrar:
# * * * * * cd /var/www/comecyt/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Verificación final

```bash
# 1. DeployCheck
cd /var/www/comecyt/apps/api && sudo -u www-data php artisan app:deploy-check

# 2. Health endpoint
HEALTH_TOKEN=$(sudo grep '^HEALTH_TOKEN=' .env | cut -d= -f2)
curl -s -H "X-Health-Token: ${HEALTH_TOKEN}" https://tudominio.gob.mx/api/health | jq

# Esperado:
# {
#   "status": "ok",
#   "checks": {"database": {"ok": true}, "cache": {"ok": true}, "storage": {"ok": true}},
#   "time": "2026-06-12T..."
# }

# 3. Frontend
curl -I https://tudominio.gob.mx | head -5
# Debe responder 200/OK con headers CSP y HSTS

# 4. Login admin (manual desde navegador)
# Ir a https://tudominio.gob.mx/login
# Email/password del admin creado por DatabaseSeeder
# CAMBIAR LA CONTRASEÑA INMEDIATAMENTE
```

---

## 9. Configurar monitoreo externo

### 9.1 UptimeRobot / Pingdom / Site24x7

Apuntar a `https://tudominio.gob.mx/api/health` con header `X-Health-Token: <tu_token>` cada 5 minutos.

Alertas a:
- Email del DPO institucional
- Slack/Teams del equipo de TIC

### 9.2 Logs centralizados (opcional)

Si tienes un SIEM institucional, enviarle:
- `/var/log/nginx/comecyt-access.log`
- `/var/log/nginx/comecyt-error.log`
- `/var/www/comecyt/apps/api/storage/logs/*.log`
- `/var/www/comecyt/apps/api/storage/logs/security-*.log` ← especialmente este

---

## 10. Mantenimiento periódico

### Diario (automático por scheduler)
- ✅ Alertas de cierre próximo de convocatorias (T-7, T-3, T-1)
- ✅ Cierre automático de convocatorias vencidas
- ✅ Limpieza de tokens expirados

### Semanal (automático por Dependabot)
- ✅ Pull requests con security updates (npm + composer)

### Mensual (manual)
- Rotar `JWT_SECRET` y `APP_KEY` (ver `docs/security/incident-response.md` §8)
- Backup completo de la DB:
  ```bash
  sudo -u postgres pg_dump comecyt_prod | gzip > /backup/comecyt-$(date +%Y%m%d).sql.gz
  sudo chmod 400 /backup/comecyt-*.sql.gz
  ```
- Revisar logs de seguridad: `sudo tail -1000 /var/www/comecyt/apps/api/storage/logs/security-*.log`

### Trimestral (manual)
- Rotar `DB_PASSWORD` (ver runbook)
- Auditoría externa (pentest, vulnerability scan)
- Revisión de roles y permisos en el sistema

---

## 11. Próximos pasos recomendados

| Mejora | Prioridad | Esfuerzo |
|---|---|---|
| Backup automatizado off-site (S3 / Azure Blob institucional) | 🔴 Alta | 2h |
| Firewall a nivel Cloudflare / Imperva (anti-DDoS L7 adicional) | 🟡 Media | 4h |
| Migrar JWT a RS256 cuando TIC entregue HSM | 🟡 Media | 1d |
| OpenTelemetry + Tempo/Jaeger para tracing | 🟢 Baja | 2d |
| Replicación PostgreSQL hot-standby | 🟡 Media | 1d |

---

## Apéndice A — Generación rápida de secretos

```bash
# DB_PASSWORD (32 bytes base64)
openssl rand -base64 32

# HEALTH_TOKEN (64 hex chars)
openssl rand -hex 32

# REVERB_APP_KEY (16 hex)
openssl rand -hex 16

# REVERB_APP_SECRET (32 hex)
openssl rand -hex 32

# VAPID keys (desde Laravel)
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan tinker --execute='
  $keys = Minishlink\WebPush\VAPID::createVapidKeys();
  echo "VAPID_PUBLIC_KEY=" . $keys["publicKey"] . PHP_EOL;
  echo "VAPID_PRIVATE_KEY=" . $keys["privateKey"] . PHP_EOL;
'
```

---

## Apéndice B — Referencias internas

- `docs/security/incident-response.md` — Runbook de IR + plan rotación secretos
- `CLAUDE.md` — Visión general del proyecto + reglas críticas
- `apps/api/deploy.sh` — Script automatizado de deploy
- `apps/api/app/Console/Commands/DeployCheck.php` — Validador pre-deploy

---

> **Para soporte técnico:** abre un Issue en el repo o contacta al equipo de TIC del COMECYT.
