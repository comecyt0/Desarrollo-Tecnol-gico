# COMECYT Sistema - Requerimientos Windows Server

**Última Actualización:** 06 Abril 2026
**Versión Sistema:** 3.1
**Status:** Production-Ready

---

## 📋 TABLA DE CONTENIDOS

1. [Requisitos de Hardware](#requisitos-de-hardware)
2. [Requisitos de Software](#requisitos-de-software)
3. [Instalación Paso a Paso](#instalación-paso-a-paso)
4. [Configuración del Sistema](#configuración-del-sistema)
5. [Verificación Post-Instalación](#verificación-post-instalación)
6. [Troubleshooting](#troubleshooting)

---

## 🖥️ REQUISITOS DE HARDWARE

### Mínimo (desarrollo/testing)
```
CPU:        2 cores @ 2.0 GHz
RAM:        4 GB
Disco:      50 GB SSD
Ancho banda: 10 Mbps
```

### Recomendado (producción)
```
CPU:        4-8 cores @ 2.4+ GHz
RAM:        16 GB
Disco:      100+ GB SSD (RAID 1 recomendado)
Ancho banda: 100+ Mbps
Network:    IP fija
```

---

## 💾 REQUISITOS DE SOFTWARE

### Sistema Operativo
```
✅ Windows Server 2016 o superior
✅ Windows Server 2019 (RECOMENDADO)
✅ Windows Server 2022
✅ Actualizar a latest patches: Windows Update

Roles necesarios (Server Manager):
  - IIS (Internet Information Services) 10.0+
  - WebSocket Protocol
  - Application Development (CGI)
```

### Backend - PHP
```
Versión:    PHP 8.3.x (REQUERIDO)
Modulos:
  ✅ php-pgsql       (PostgreSQL)
  ✅ php-curl        (API requests)
  ✅ php-json        (JSON parsing)
  ✅ php-mbstring    (String functions)
  ✅ php-tokenizer   (Code parsing)
  ✅ php-xml         (XML parsing)
  ✅ php-fileinfo    (File MIME detection)
  ✅ php-zip         (ZIP compression)
  ✅ php-gd          (Image processing, PDFs)
  ✅ php-opcache     (Performance)

Memory:     php.ini memory_limit = 512M (mínimo 256M)
Timeout:    max_execution_time = 300 segundos
Upload:     upload_max_filesize = 50M
Post:       post_max_size = 50M

Instalación: https://www.php.net/downloads.php
             Descargar: "Windows downloads" → "ZIP file"
             O usar: https://www.apachelounge.com/
```

### Frontend - Node.js & npm
```
Node.js:    18.x LTS o 20.x LTS (RECOMENDADO)
npm:        9.x o superior (viene con Node.js)

Instalación: https://nodejs.org/
             Descargar: LTS version
             Incluye npm automáticamente
```

### Base de Datos
```
PostgreSQL: 14.x, 15.x o 16.x (RECOMENDADO)
Puerto:     5432 (default)
Usuario:    comecyt (crear)
Password:   [generar password seguro]
Database:   comecyt_production

Instalación: https://www.postgresql.org/download/windows/
             Durante instalación:
             - Guardar password de postgres
             - Recordar puerto (default 5432)
             - Instalar PgAdmin 4 (admin tool)
```

### Herramientas Adicionales

```
Composer:   2.6.x (PHP dependency manager)
            Descargar: https://getcomposer.org/download/
            Instalar como: "Composer-Setup.exe"

Git:        2.4x (version control)
            Descargar: https://git-scm.com/download/win
            O: https://github.com/git-for-windows/git/releases

cURL:       Incluido en PHP, pero verificar disponible en PATH
```

---

## 🚀 INSTALACIÓN PASO A PASO

### PASO 0: Estructura de Carpetas Base

```
C:\
├── apps\
│   ├── comecyt\              ← Proyecto raíz
│   │   ├── api\             ← Backend Laravel
│   │   ├── web\             ← Frontend Next.js
│   │   └── ...
│   ├── php-8.3\             ← PHP binarios
│   └── node_versions\       ← Node.js (opcional)
├── databases\
│   └── postgresql\          ← PostgreSQL data
└── repos\
    └── comecyt-backup\      ← Backups
```

**Crear carpetas:**
```powershell
# PowerShell (Admin)
mkdir C:\apps\comecyt
mkdir C:\apps\php-8.3
mkdir C:\databases
mkdir C:\repos\comecyt-backup
```

---

### PASO 1: Instalar PostgreSQL

1. **Descargar:** https://www.postgresql.org/download/windows/
2. **Ejecutar:** `postgresql-16-x64-windows.exe`
3. **Configuración Instalación:**
   - Carpeta: `C:\Program Files\PostgreSQL\16`
   - Puerto: `5432` (default)
   - Password superuser: `[GENERAR PASSWORD SEGURO]`
   - Locale: Spanish, Mexico
   - ✅ Instalar pgAdmin 4
4. **Después de instalar:**
   ```powershell
   # Verificar instalación
   "C:\Program Files\PostgreSQL\16\bin\psql.exe" --version
   # Debería mostrar: psql (PostgreSQL) 16.x
   ```

5. **Crear usuario y base de datos:**
   ```powershell
   # Abrir pgAdmin 4 → Crear usuario comecyt
   # O usar línea de comandos:

   $env:PGPASSWORD = "postgres_password"
   & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h localhost -c "CREATE USER comecyt WITH PASSWORD 'comecyt_password';"
   & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h localhost -c "CREATE DATABASE comecyt_production OWNER comecyt;"
   & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U postgres -h localhost -c "ALTER USER comecyt CREATEDB;"
   ```

**Documentar credenciales:**
```env
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=comecyt_production
DB_USERNAME=comecyt
DB_PASSWORD=comecyt_password
```

---

### PASO 2: Instalar Node.js & npm

1. **Descargar:** https://nodejs.org/ → **LTS versión**
2. **Ejecutar:** `node-v20.x.x-x64.msi`
3. **Configuración:**
   - Instalar en: `C:\Program Files\nodejs`
   - ✅ Add to PATH
   - ✅ npm package manager
4. **Verificar instalación:**
   ```powershell
   node --version    # v20.x.x
   npm --version     # 10.x.x
   ```

---

### PASO 3: Instalar PHP 8.3

**Opción A: Non-Thread-Safe (Recomendado para IIS)**

1. **Descargar:** https://www.php.net/downloads.php
   - Buscar: PHP 8.3.x → Windows downloads
   - Seleccionar: **`php-8.3.x-nts-Win32-vs16-x64.zip`** (NTS = Non-Thread-Safe)

2. **Extraer:**
   ```powershell
   # Extraer a C:\apps\php-8.3
   Expand-Archive -Path php-8.3.x-nts-Win32-vs16-x64.zip -DestinationPath C:\apps\php-8.3
   ```

3. **Configurar php.ini:**
   ```powershell
   # Copiar template
   Copy-Item C:\apps\php-8.3\php.ini-production C:\apps\php-8.3\php.ini

   # Editar php.ini con Notepad:
   # C:\apps\php-8.3\php.ini
   ```

   **Cambios en php.ini:**
   ```ini
   ; Timezone
   date.timezone = America/Mexico_City

   ; Memoria
   memory_limit = 512M

   ; Uploads
   upload_max_filesize = 50M
   post_max_size = 50M

   ; Extensions
   extension=curl
   extension=fileinfo
   extension=gd
   extension=json
   extension=mbstring
   extension=pdo_pgsql
   extension=xml
   extension=zip
   extension=pgsql

   ; Performance
   opcache.enable = 1
   opcache.memory_consumption = 128
   ```

4. **Agregar a PATH:**
   ```powershell
   # PowerShell (Admin)
   $env:Path += ";C:\apps\php-8.3"
   [Environment]::SetEnvironmentVariable("Path", $env:Path, "Machine")

   # Verificar en nueva terminal:
   php --version    # PHP 8.3.x
   php -m           # Listar extensiones cargadas
   ```

5. **Instalar Composer:**
   - Descargar: https://getcomposer.org/download/
   - Ejecutar: `Composer-Setup.exe`
   - Debería detectar PHP automáticamente
   - Verificar:
   ```powershell
   composer --version    # Composer 2.6.x
   ```

---

### PASO 4: Clonar Repositorio

```powershell
# En carpeta C:\apps\
cd C:\apps
git clone https://github.com/tu-usuario/comecyt-system.git
cd comecyt-system
```

**Si no tienes acceso a GitHub:**
- Copiar carpeta del proyecto manualmente a `C:\apps\comecyt-system`
- O: Descomprimir archivo ZIP proporcionado

---

### PASO 5: Instalar Dependencias Backend

```powershell
cd C:\apps\comecyt-system\apps\api

# Instalar PHP dependencies
composer install

# Si hay error de version:
composer install --ignore-platform-reqs
```

**Espera esperada:** 3-5 minutos

---

### PASO 6: Configurar Backend (.env)

```powershell
# Copiar template
Copy-Item .env.example .env
```

**Editar `C:\apps\comecyt-system\apps\api\.env`:**

```env
APP_NAME="COMECYT Sistema"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://tu-dominio.com.mx
APP_TIMEZONE=America/Mexico_City

# Database
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=comecyt_production
DB_USERNAME=comecyt
DB_PASSWORD=comecyt_password

# JWT Security
JWT_SECRET=[generar nuevo con: composer require php-open-source-saver/jwt-auth]
JWT_ALGO=HS256
JWT_TTL=1440

# Mail (desarrollo: log, producción: smtp real)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@comecyt.gob.mx
MAIL_FROM_NAME="COMECYT Sistema"

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug

# Cache
CACHE_STORE=file

# Queue
QUEUE_CONNECTION=database

# Security
RATE_LIMIT_MAX=100
RATE_LIMIT_WINDOW=60
AUTH_LOGIN_RATE_LIMIT=5
AUTH_LOGIN_RATE_WINDOW=60
```

**Generar Application Key:**
```powershell
php artisan key:generate
# Salida: Application key [...] set successfully.
```

---

### PASO 7: Ejecutar Migraciones

```powershell
# Desde C:\apps\comecyt-system\apps\api\

# Verificar conexión DB
php artisan migrate:status
# Debería mostrar: "Migration table not found. Migrating..."

# Ejecutar migraciones
php artisan migrate --seed
# Esperar 2-3 minutos

# Verificar usuarios de prueba creados
php artisan tinker
>>> User::all();
>>> exit
```

---

### PASO 8: Instalar Dependencias Frontend

```powershell
cd C:\apps\comecyt-system\apps\web

npm install
# Espera esperada: 3-5 minutos
```

---

### PASO 9: Configurar Frontend (.env.local)

```powershell
# Crear archivo
New-Item .env.local -Force
```

**Contenido de `.env.local`:**
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
# O en producción:
# NEXT_PUBLIC_API_URL=https://api.tu-dominio.com.mx/api
```

---

### PASO 10: Build Frontend

```powershell
# Desde apps/web/

npm run build
# Espera esperada: 2-3 minutos

# Verificar output (debería decir "compiled successfully")
```

---

## 🔧 CONFIGURACIÓN DEL SISTEMA

### Opción A: Development (IIS + PHP Manager)

**1. Instalar IIS con CGI:**
```powershell
# PowerShell (Admin)
Add-WindowsFeature Web-Server, Web-CGI, Web-Asp-Net45

# Descargar PHP Manager (opcional pero muy útil)
# https://phpmanager.codeplex.com/
# O más nuevo: https://github.com/phpmanager/phpmanager
```

**2. Configurar sitio IIS:**
- Abrir IIS Manager
- Crear nuevo sitio (Site)
  - Nombre: `COMECYT-API`
  - Carpeta: `C:\apps\comecyt-system\apps\api\public`
  - Port: 8000
- Crear nuevo sitio para Frontend
  - Nombre: `COMECYT-WEB`
  - Carpeta: `C:\apps\comecyt-system\apps\web\.next`
  - Port: 3000

**3. Verificar puertos:**
```powershell
netstat -ano | findstr :8000
netstat -ano | findstr :3000
```

---

### Opción B: Producción (Nginx + PM2)

**1. Instalar Nginx para Windows:**
```powershell
# Descargar: http://nginx.org/en/download.html
# Extraer a: C:\nginx

# Crear servicio Windows para autostart
# O usar: NSSM (Non-Sucking Service Manager)
```

**2. Configurar PHP-FPM:**
```powershell
# En cmd:
cd C:\apps\php-8.3
php-cgi.exe -b 127.0.0.1:9000

# O crear servicio Windows con NSSM
```

**3. Instalar PM2 para Node.js:**
```powershell
npm install -g pm2

# Desde apps/web/
pm2 start npm --name "comecyt-web" -- start
pm2 save
pm2 startup
```

---

## ✅ VERIFICACIÓN POST-INSTALACIÓN

### 1. Verificar Backend

```powershell
cd C:\apps\comecyt-system\apps\api

# Test API
php artisan serve --host=0.0.0.0 --port=8000
```

**En navegador:** http://localhost:8000/api/catalogs/programa/1/campos
- Debería retornar JSON con estructura de programa

---

### 2. Verificar Frontend

```powershell
cd C:\apps\comecyt-system\apps\web

# Test build
npm run start
```

**En navegador:** http://localhost:3000
- Debería mostrar login page

---

### 3. Verificar Base de Datos

```powershell
$env:PGPASSWORD = "comecyt_password"
& "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U comecyt -h localhost -d comecyt_production -c "SELECT COUNT(*) FROM users;"

# Debería retornar: count
# -------
#     4
```

---

### 4. Test Login Completo

1. **Iniciar Backend:**
   ```powershell
   cd C:\apps\comecyt-system\apps\api
   php artisan serve --host=0.0.0.0 --port=8000
   ```

2. **Iniciar Frontend (otra terminal):**
   ```powershell
   cd C:\apps\comecyt-system\apps\web
   npm run start
   ```

3. **En navegador:**
   ```
   http://localhost:3000
   ```

4. **Login con:**
   ```
   Email:    admin@comecyt.gob.mx
   Password: password123
   ```

---

## 📊 CHECKLIST FINAL

```
☐ Windows Server actualizado
☐ PostgreSQL 16 instalado y corriendo
☐ Node.js 20 LTS instalado
☐ PHP 8.3 instalado con extensiones
☐ Composer instalado
☐ Git instalado
☐ Repositorio clonado en C:\apps\comecyt-system
☐ Composer install completado (backend)
☐ .env configurado con credenciales DB
☐ Migraciones ejecutadas (migrate --seed)
☐ npm install completado (frontend)
☐ .env.local configurado
☐ npm run build completado sin errores
☐ API inicia con: php artisan serve
☐ Frontend inicia con: npm run start
☐ Login funciona con credenciales test
☐ Dashboard carga sin errores
```

---

## 🚨 TROUBLESHOOTING

### Error: "The "-web" extension was not found"

**Solución:**
```powershell
# Habilitar extensión en IIS
Add-WindowsFeature Web-Asp-Net45
```

---

### Error: "SQLSTATE[08006]"

**Significa:** No puede conectar a PostgreSQL

**Soluciones:**
1. Verificar PostgreSQL está corriendo:
   ```powershell
   Get-Service postgresql*
   ```

2. Verificar credenciales en .env:
   ```powershell
   $env:PGPASSWORD = "comecyt_password"
   & "C:\Program Files\PostgreSQL\16\bin\psql.exe" -U comecyt -h localhost
   ```

3. Verificar puerto 5432 no está bloqueado:
   ```powershell
   netstat -ano | findstr :5432
   ```

---

### Error: "npm ERR! code EACCES"

**Significa:** Permisos insuficientes

**Solución:**
```powershell
# Ejecutar PowerShell como Admin
# O cambiar permisos carpeta node_modules
icacls C:\apps\comecyt-system /grant:r %username%:F /t
```

---

### Error: "502 Bad Gateway"

**Significa:** Backend no está respondiendo

**Verificar:**
```powershell
# 1. Backend corriendo?
netstat -ano | findstr :8000

# 2. PHP-FPM corriendo?
Get-Process | findstr php

# 3. Errores en logs?
cd C:\apps\comecyt-system\apps\api
cat storage/logs/laravel.log
```

---

## 📞 SOPORTE

Si encuentras problemas:

1. **Verificar logs:**
   ```powershell
   # Backend
   cat C:\apps\comecyt-system\apps\api\storage\logs\laravel.log

   # Frontend
   # Abrir DevTools (F12) en navegador
   ```

2. **Verificar servicios:**
   ```powershell
   Get-Service postgresql* | Start-Service  # Iniciar PostgreSQL
   php artisan serve                        # Iniciar Laravel
   npm run start                            # Iniciar Next.js
   ```

3. **Reset limpio:**
   ```powershell
   cd C:\apps\comecyt-system\apps\api
   php artisan migrate:fresh --seed
   ```

---

**Documento preparado para instalación Production en Windows Server**
**Versión: 1.0 | Fecha: 06 Abril 2026**
