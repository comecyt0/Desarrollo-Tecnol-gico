# COMECYT - Windows Server Quick Setup (TL;DR)

**Tiempo estimado:** 2-3 horas en servidor limpio
**Versión:** 1.0

---

## ⚡ INSTALACIÓN EXPRESS

### Requisitos Mínimos
```
- Windows Server 2016+ (recomendado 2019)
- 4GB RAM (8GB para producción)
- 50GB disco
- Internet conexión
- Usuario Admin en servidor
```

---

## 📦 INSTALACIÓN (copiar y ejecutar)

### 1️⃣ PostgreSQL (15 min)

```powershell
# Descargar: https://www.postgresql.org/download/windows/
# Ejecutar: postgresql-16-x64-windows.exe

# Configurar:
# - Puerto: 5432
# - Password: [anota el password de postgres]
# - Locale: Spanish, Mexico

# Crear usuario y BD:
$env:PGPASSWORD = "tu_postgres_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h localhost -c "CREATE USER comecyt WITH PASSWORD 'comecyt_prod';"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h localhost -c "CREATE DATABASE comecyt_production OWNER comecyt;"
```

✅ **Verificación:**
```powershell
netstat -ano | findstr :5432
# Debería mostrar LISTENING
```

---

### 2️⃣ Node.js (10 min)

```powershell
# Descargar: https://nodejs.org/ → LTS
# Ejecutar: node-v20.x.x-x64.msi
# Seleccionar: Add to PATH durante instalación

# Verificar:
node --version    # v20.x.x
npm --version     # 10.x.x
```

---

### 3️⃣ PHP 8.3 (10 min)

```powershell
# Descargar: https://www.php.net/downloads.php
# → Windows downloads → php-8.3.x-nts-Win32-vs16-x64.zip

# Extraer:
Expand-Archive -Path php-8.3.x-nts-Win32-vs16-x64.zip -DestinationPath C:\php

# Agregar a PATH:
$env:Path += ";C:\php"
[Environment]::SetEnvironmentVariable("Path", $env:Path, "Machine")

# Editar C:\php\php.ini:
# date.timezone = America/Mexico_City
# memory_limit = 512M
# upload_max_filesize = 50M
# extension=pdo_pgsql (uncomment)
# extension=curl (uncomment)
# extension=gd (uncomment)

# Verificar:
php --version
php -m | findstr pgsql
```

---

### 4️⃣ Composer (5 min)

```powershell
# Descargar: https://getcomposer.org/download/
# Ejecutar: Composer-Setup.exe

# Debería detectar PHP automáticamente
# Verificar:
composer --version
```

---

### 5️⃣ Clonar Repositorio (5 min)

```powershell
# Opción A: Si tienes Git
git clone https://github.com/tu-usuario/comecyt-system.git C:\apps\comecyt

# Opción B: Copiar carpeta del proyecto manualmente
# Copiar a: C:\apps\comecyt-system

cd C:\apps\comecyt-system
```

---

### 6️⃣ Instalar Backend (20 min)

```powershell
cd C:\apps\comecyt-system\apps\api

# Copiar configuración
Copy-Item .env.example .env

# Editar .env con credenciales:
# DB_HOST=localhost
# DB_PORT=5432
# DB_DATABASE=comecyt_production
# DB_USERNAME=comecyt
# DB_PASSWORD=comecyt_prod

# Instalar dependencias
composer install

# Generar key
php artisan key:generate

# Migraciones
php artisan migrate --seed

# Verificar:
php artisan tinker
>>> User::count()
>>> exit
```

✅ **Debería mostrar 4 usuarios**

---

### 7️⃣ Instalar Frontend (15 min)

```powershell
cd C:\apps\comecyt-system\apps\web

# Instalar
npm install

# Configurar .env.local
New-Item .env.local -Force
# Contenido: NEXT_PUBLIC_API_URL=http://localhost:8000/api

# Build
npm run build

# Debería mostrar: "compiled successfully"
```

---

## ✅ VERIFICACIÓN (10 min)

### Terminal 1: Iniciar Backend
```powershell
cd C:\apps\comecyt-system\apps\api
php artisan serve --host=0.0.0.0 --port=8000
```

✅ Debería mostrar: `Server running on http://localhost:8000`

### Terminal 2: Iniciar Frontend
```powershell
cd C:\apps\comecyt-system\apps\web
npm run start
```

✅ Debería mostrar: `- Local: http://localhost:3000`

### Terminal 3: Test API
```powershell
# En otra terminal, hacer request
$response = Invoke-WebRequest -Uri http://localhost:8000/api/catalogs/programa/1/campos
$response.StatusCode    # Debería mostrar: 200
```

### Navegador: http://localhost:3000

✅ Debería mostrar: Login page

**Login:**
```
Email:    admin@comecyt.gob.mx
Password: password123
```

✅ Debería entrar al dashboard

---

## 🚀 SETUP PRODUCTION (RESUMIDO)

```powershell
# 1. .env para production
APP_ENV=production
APP_DEBUG=false
JWT_SECRET=[NUEVO]
DB_PASSWORD=[SEGURO]

# 2. Generar nueva APP_KEY
php artisan key:generate

# 3. Build optimizado
npm run build

# 4. Configurar Nginx/IIS con SSL

# 5. Crear daily backup task (PowerShell Scheduler)

# 6. Crear health check endpoint

# 7. Configurar logs rotation

# 8. Test: https://tu-dominio.com.mx
```

---

## 🔧 COMANDOS ÚTILES

```powershell
# Backend
cd C:\apps\comecyt-system\apps\api

php artisan serve                    # Start server
php artisan tinker                   # CLI interactiva
php artisan migrate --fresh --seed   # Reset DB
php artisan optimize                 # Cache optimization
php artisan config:cache             # Cache config

# Frontend
cd C:\apps\comecyt-system\apps\web

npm run dev                          # Dev mode
npm run build                        # Production build
npm run start                        # Run built app
npm run lint                         # Check errors

# Database
$env:PGPASSWORD = "comecyt_prod"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U comecyt -h localhost -d comecyt_production
```

---

## 🚨 PROBLEMAS COMUNES

### "Cannot find module"
```powershell
cd apps/web
npm install
npm run build
```

### "SQLSTATE[08006]"
```powershell
# Verificar PostgreSQL está corriendo
Get-Service postgresql*

# Verificar credenciales .env
$env:PGPASSWORD = "comecyt_prod"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U comecyt -h localhost
```

### "Connection refused on port 8000"
```powershell
# Verificar PHP está corriendo
Get-Process | findstr php

# Matarlo y reiniciar
Stop-Process -Name "php.exe" -Force
cd apps\api
php artisan serve --host=0.0.0.0 --port=8000
```

### "EACCES: permission denied"
```powershell
# Ejecutar PowerShell como Admin
Start-Process powershell -Verb runAs
```

---

## 📚 DOCUMENTACIÓN COMPLETA

Para más detalles, ver:
- `WINDOWS_SERVER_REQUIREMENTS.md` - Instalación detallada
- `WINDOWS_SERVER_PRODUCTION_GUIDE.md` - Configuración production
- `CLAUDE.md` - Arquitectura y patrones de código

---

## ✅ QUICK CHECKLIST

```
☐ PostgreSQL 16 instalado y corriendo
☐ Node.js 20 LTS instalado
☐ PHP 8.3 instalado con extensiones
☐ Composer instalado
☐ Repositorio clonado en C:\apps\comecyt-system
☐ Backend .env configurado
☐ composer install completado
☐ Migraciones ejecutadas
☐ Frontend npm install completado
☐ npm run build completado
☐ php artisan serve funciona (puerto 8000)
☐ npm run start funciona (puerto 3000)
☐ http://localhost:3000 muestra login
☐ Login con admin@comecyt.gob.mx / password123 funciona
☐ Dashboard carga sin errores
```

---

## ⏱️ TIMELINE

```
Total instalación: 2-3 horas

1. PostgreSQL:      15 min
2. Node.js:         10 min
3. PHP:             10 min
4. Composer:        5 min
5. Clone repo:      5 min
6. Backend setup:   20 min
7. Frontend setup:  15 min
8. Verification:    10 min
9. Production:      30 min (opcional)
```

---

**¿Problemas? Ver WINDOWS_SERVER_REQUIREMENTS.md troubleshooting section**
