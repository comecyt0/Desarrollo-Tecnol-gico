# COMECYT - Production Deployment Guide (Windows Server)

**Fecha:** 06 Abril 2026
**Versión:** 1.0
**Audience:** DevOps, System Administrators

---

## 📋 OVERVIEW

Este documento cubre:
- Configuración production-ready
- Optimizaciones de performance
- Seguridad y hardening
- Monitoring y alertas
- Backup y disaster recovery
- Escalabilidad

---

## 🏗️ ARQUITECTURA RECOMENDADA

### Topología Simple (1 servidor)

```
┌─────────────────────────────────────────┐
│         Windows Server 2019+            │
├─────────────────────────────────────────┤
│  IIS/Nginx                              │
│  ├─ Frontend (Next.js - SSR)            │
│  └─ Backend (Laravel - PHP-FPM)         │
├─────────────────────────────────────────┤
│  PostgreSQL 16 (local)                  │
│  ├─ DB: comecyt_production              │
│  └─ User: comecyt                       │
├─────────────────────────────────────────┤
│  Storage                                │
│  ├─ C:\apps\comecyt-system (código)     │
│  ├─ C:\storage\documentos (files)       │
│  └─ C:\backup (daily backups)           │
└─────────────────────────────────────────┘
```

### Topología Escalada (3+ servidores)

```
┌──────────────────────────────────────────────────────┐
│             Load Balancer (Nginx)                    │
│             (Windows Server o cloud)                 │
└──────────────┬───────────────────────────────────────┘
               │
       ┌───────┼───────┐
       ↓       ↓       ↓
    WEB-01  WEB-02  WEB-03
    (IIS)   (IIS)   (IIS)
    ├─WEB   ├─WEB   ├─WEB
    └─API   └─API   └─API
       │       │       │
       └───────┼───────┘
               ↓
    ┌──────────────────┐
    │  PostgreSQL      │
    │  (dedicated)     │
    └──────────────────┘
```

---

## 🔐 SEGURIDAD PRODUCTION

### 1. Network Security

```
Firewall (Windows Defender Firewall):

Inbound Rules:
  ✅ Port 80 (HTTP)      - Para redirección a HTTPS
  ✅ Port 443 (HTTPS)    - Tráfico encriptado
  ❌ Port 8000 (API)     - Cerrado (solo internos)
  ❌ Port 3000 (Next.js) - Cerrado (reverse proxy)
  ❌ Port 5432 (DB)      - Cerrado (solo localhost)
```

**Configurar firewall:**
```powershell
# PowerShell (Admin)

# Permitir HTTPS
New-NetFirewallRule -DisplayName "HTTPS" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 443

# Permitir HTTP (solo para redirección)
New-NetFirewallRule -DisplayName "HTTP" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 80

# Cerrar puerto 8000 (API debe ir detrás de reverse proxy)
New-NetFirewallRule -DisplayName "Bloquear API" -Direction Inbound -Action Block -Protocol TCP -LocalPort 8000
```

---

### 2. SSL/TLS Certificate

**Opción A: Let's Encrypt (Gratuito, Recomendado)**

```powershell
# Instalar Certbot
# https://certbot.eff.org/instructions?ws=iis&os=windows

# Obtener certificado
certbot certonly --standalone -d tu-dominio.com.mx

# Renovación automática (cada 90 días)
certbot renew --dry-run
```

**Opción B: Self-signed (Development)**

```powershell
# PowerShell (Admin)
$cert = New-SelfSignedCertificate `
  -CertStoreLocation cert:\LocalMachine\My `
  -DnsName tu-dominio.com.mx `
  -FriendlyName "COMECYT"

$cert | Get-ChildItem |
  Export-PfxCertificate -FilePath C:\certs\comecyt.pfx -Password (ConvertTo-SecureString -String "password" -AsPlainText -Force)
```

---

### 3. Environment Variables

**NUNCA hardcodear secretos:**

```powershell
# En .env.production (NO en código)
APP_KEY=base64:xxxxxxxx              # Generar nuevo
JWT_SECRET=xxxxxxxx                  # Generar nuevo: openssl rand -base64 32
DB_PASSWORD=xxxx                     # Password fuerte (mínimo 16 chars)
MAIL_PASSWORD=xxxx                   # Si usas SMTP autenticado
```

**Generar secrets:**

```powershell
# En PowerShell
[System.Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes((New-Guid).ToString()))

# O en Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

---

### 4. Database Security

```sql
-- Como postgres user
CREATE USER comecyt_prod WITH PASSWORD 'ComEcYt!Prod2026#Strong';
CREATE DATABASE comecyt_production OWNER comecyt_prod;
ALTER USER comecyt_prod CREATEDB;

-- Permisos mínimos
GRANT CONNECT ON DATABASE comecyt_production TO comecyt_prod;
GRANT ALL PRIVILEGES ON SCHEMA public TO comecyt_prod;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO comecyt_prod;
```

**Configurar acceso:**

```powershell
# En pg_hba.conf (C:\Program Files\PostgreSQL\16\data\)
# Cambiar auth method a: md5 o scram-sha-256

# Permitir solo localhost
# host    comecyt_production    comecyt_prod    127.0.0.1/32    scram-sha-256
```

---

## ⚙️ OPTIMIZACIONES PERFORMANCE

### 1. PHP Optimization

**php.ini ajustes production:**

```ini
; Performance
opcache.enable = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0         ; En producción
opcache.revalidate_freq = 0

; Security
expose_php = Off
disable_functions = exec,shell_exec,passthru,system,proc_open,popen
max_input_vars = 5000

; Memory
memory_limit = 512M

; Timeout
max_execution_time = 300

; Session
session.gc_probability = 1
session.gc_divisor = 100
session.gc_maxlifetime = 86400
```

---

### 2. PostgreSQL Optimization

**postgresql.conf:**

```ini
# Memoria
shared_buffers = 4GB                  # 1/4 de RAM disponible
effective_cache_size = 12GB            # 3/4 de RAM
maintenance_work_mem = 1GB

# Conexiones
max_connections = 200
max_prepared_transactions = 50

# WAL
wal_buffers = 16MB
checkpoint_timeout = 15min

# Configuración automática (para 16GB RAM)
# Visitar: https://pgtune.leopard.in.ua/
```

**Después de cambiar:**
```powershell
# Reiniciar PostgreSQL
Restart-Service postgresql-x64-16
```

---

### 3. Next.js Optimization

```bash
# .next/config.js (production)
{
  images: {
    minimumCacheTTL: 31536000,       // 1 year cache
  },
  compress: true,
  swcMinify: true,
  productionBrowserSourceMaps: false,
}
```

---

### 4. Nginx Reverse Proxy

**nginx.conf:**

```nginx
upstream laravel_backend {
    server localhost:8000;
    keepalive 32;
}

upstream nextjs_frontend {
    server localhost:3000;
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.com.mx;

    ssl_certificate /etc/ssl/certs/comecyt.crt;
    ssl_certificate_key /etc/ssl/private/comecyt.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Gzip compression
    gzip on;
    gzip_types text/plain text/css text/xml text/javascript
               application/x-javascript application/xml+rss
               application/javascript application/json;
    gzip_min_length 1000;

    # Frontend
    location / {
        proxy_pass http://nextjs_frontend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    }

    # API Backend
    location /api/ {
        proxy_pass http://laravel_backend/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        # CORS headers
        add_header 'Access-Control-Allow-Origin' '*' always;
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, DELETE, OPTIONS' always;
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization' always;
    }

    # Static files cache
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}

# Redireccionar HTTP a HTTPS
server {
    listen 80;
    server_name tu-dominio.com.mx;
    return 301 https://$server_name$request_uri;
}
```

---

## 📊 MONITORING & LOGGING

### 1. Application Logging

**Laravel logs:**
```powershell
# En producción, logs van a:
# C:\apps\comecyt-system\apps\api\storage\logs\laravel.log

# Rotación de logs (crear tarea scheduled):
LogRotate-Script.ps1

# Monitoreo:
Get-Content -Path "C:\apps\comecyt-system\apps\api\storage\logs\laravel.log" -Tail 100 -Wait
```

**Sentry Integration (opcional pero recomendado):**
```bash
# En composer.json
composer require sentry/sentry-laravel

# En .env
SENTRY_LARAVEL_DSN=https://xxxxx@sentry.io/xxxxx
SENTRY_ENVIRONMENT=production
```

---

### 2. System Monitoring

**Task Manager alternativa: Resource Monitor**

```powershell
# Monitorear recursos en tiempo real
Get-Counter -Counter "\Processor(_Total)\% Processor Time" -SampleInterval 1 -MaxSamples 10

# Monitorear memoria
Get-Counter -Counter "\Memory\Available MBytes"

# Monitorear disco
Get-Volume -DriveLetter C | Select-Object SizeRemaining, Size
```

**Crear alertas (PowerShell Task Scheduler):**

```powershell
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date).AddHours(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "C:\scripts\health-check.ps1"
Register-ScheduledTask -TaskName "COMECYT-HealthCheck" -Trigger $trigger -Action $action
```

---

## 💾 BACKUP & RECOVERY

### 1. Database Backups

**Script diario:**

```powershell
# C:\scripts\backup-db.ps1

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$backupfile = "C:\backup\comecyt_prod_$timestamp.sql"

$env:PGPASSWORD = "comecyt_password"
& "C:\Program Files\PostgreSQL\16\bin\pg_dump.exe" `
  -U comecyt `
  -h localhost `
  -d comecyt_production `
  -F p `
  -f $backupfile

# Comprimir
Compress-Archive -Path $backupfile -DestinationPath "$backupfile.zip" -Force
Remove-Item $backupfile

# Mantener últimos 30 días
Get-ChildItem "C:\backup\comecyt_prod_*.sql.zip" |
  Where-Object {$_.LastWriteTime -lt (Get-Date).AddDays(-30)} |
  Remove-Item
```

**Ejecutar diariamente:**

```powershell
# Task Scheduler
$trigger = New-ScheduledTaskTrigger -Daily -At 2:00AM
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
  -Argument "-File C:\scripts\backup-db.ps1"
Register-ScheduledTask -TaskName "COMECYT-DBBackup" -Trigger $trigger -Action $action
```

---

### 2. Application Code Backups

```powershell
# Script semanal
$timestamp = Get-Date -Format "yyyy-MM-dd"
$zipfile = "C:\backup\comecyt_code_$timestamp.zip"

Compress-Archive -Path "C:\apps\comecyt-system" `
  -DestinationPath $zipfile `
  -CompressionLevel Optimal `
  -Force

# Upload a cloud (AWS S3, Azure Blob, etc.)
aws s3 cp $zipfile s3://my-bucket/backups/
```

---

### 3. Recovery Procedure

**Database recovery:**

```powershell
# 1. Detener servicios
Stop-Service postgresql-x64-16

# 2. Restaurar base de datos
$env:PGPASSWORD = "comecyt_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" `
  -U comecyt `
  -d comecyt_production `
  -f C:\backup\comecyt_prod_yyyy-MM-dd.sql

# 3. Reiniciar
Start-Service postgresql-x64-16
```

**Application recovery:**

```powershell
# 1. Borrar carpeta actual
Remove-Item -Recurse "C:\apps\comecyt-system" -Force

# 2. Restaurar desde backup
Expand-Archive -Path "C:\backup\comecyt_code_yyyy-MM-dd.zip" `
  -DestinationPath "C:\apps"

# 3. Reinstalar dependencias
cd C:\apps\comecyt-system\apps\api
composer install --no-dev
composer dump-autoload --optimize

# 4. Reiniciar servicios
Restart-Service -Name "IIS-AppPool-COMECYT*"
```

---

## 🔄 CONTINUOUS DEPLOYMENT

### Opción 1: GitHub Actions (Recomendado)

```yaml
# .github/workflows/deploy-windows.yml

name: Deploy to Windows Server

on:
  push:
    branches: [main]

jobs:
  deploy:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Deploy to Windows Server
      uses: appleboy/ssh-action@master
      with:
        host: ${{ secrets.SERVER_IP }}
        username: ${{ secrets.SERVER_USER }}
        password: ${{ secrets.SERVER_PASSWORD }}
        script: |
          cd C:\apps\comecyt-system
          git pull origin main
          cd apps\api
          composer install --no-dev
          php artisan migrate --force
          cd ..\web
          npm install
          npm run build
          # Restart services
```

### Opción 2: Webhook con PowerShell

```powershell
# C:\scripts\webhook-deploy.ps1

param(
    [string]$Ref
)

if ($Ref -eq "refs/heads/main") {
    cd C:\apps\comecyt-system
    git pull origin main

    # Backend
    cd apps\api
    composer install --no-dev --optimize-autoloader
    php artisan migrate --force
    php artisan cache:clear

    # Frontend
    cd ..\web
    npm install
    npm run build

    # Log success
    "Deployment completed: $(Get-Date)" | Out-File -Append C:\logs\deployments.log
}
```

---

## 📈 ESCALABILIDAD

### Aumentar de 1 a 3 servidores

**Paso 1: Load Balancer**
```
Instalar Nginx en servidor separado
Configurar upstream servers (3 WEB servers)
```

**Paso 2: Replicar código**
```powershell
# En cada WEB server:
git clone ... C:\apps\comecyt-system
composer install --no-dev
npm run build
```

**Paso 3: DB centralizada**
```
Migrar PostgreSQL a servidor dedicado
Actualizar .env en cada WEB server con nueva DB_HOST
```

**Paso 4: Shared storage**
```powershell
# Para documentos (storage/documentos)
# Opción A: NFS mount
mount -o rw \\storage-server\documentos Z:

# Opción B: Sync con S3/Azure Blob
```

---

## ✅ PRE-PRODUCTION CHECKLIST

```
SECURITY:
☐ SSL/TLS certificado instalado
☐ Firewall configurado (solo 80, 443 abiertos)
☐ .env.production con secretos nuevos
☐ APP_DEBUG = false
☐ APP_ENV = production
☐ DB_PASSWORD mínimo 16 caracteres
☐ Permisos de archivos restringidos (755)

PERFORMANCE:
☐ PHP opcache habilitado
☐ PostgreSQL optimizado
☐ Nginx/IIS reverse proxy configurado
☐ Gzip compression habilitado
☐ CDN configurado (opcional)
☐ npm run build completado sin errores

BACKUPS:
☐ Script de backup DB creado
☐ Task Scheduler configurado (diario 2AM)
☐ Backup manual ejecutado
☐ Recovery procedure testeado
☐ Storage backup off-site disponible

MONITORING:
☐ Logs habilitados (no demasiado verbose)
☐ Health check endpoint configurado
☐ Alertas de disco bajo configuradas
☐ Alertas de CPU/memoria configuradas
☐ Sentry o error tracking integrado

TESTING:
☐ E2E testing completado
☐ Load testing 100+ usuarios simultáneos
☐ Failover testing realizado
☐ Recovery testing completado
☐ Penetration testing (opcional pero recomendado)
```

---

## 📞 SOPORTE PRODUCTION

**En caso de emergencia:**

```powershell
# 1. Verificar qué está caído
Get-Service | Where-Object {$_.Status -ne "Running"}

# 2. Logs de errores
cat "C:\apps\comecyt-system\apps\api\storage\logs\laravel.log"

# 3. Reiniciar servicios
Restart-Service postgresql-x64-16
Restart-Service nginx
Restart-Service w3svc

# 4. Si nada funciona: restore desde backup
# Ver sección "Recovery Procedure" arriba
```

---

**Documento versión 1.0 | 06 Abril 2026**
**Para preguntas: consulta con DevOps team**
