# 🪟 WINDOWS_DEPLOYMENT.md — Despliegue de COMECYT en Windows Server 2022

> Guía de despliegue **nativo en Windows** del sistema *Gestión de Proyectos de Desarrollo
> Tecnológico y Vinculación* (COMECYT). Escrita para el próximo TIC que herede este servidor.
> El repo trae `bootstrap.sh` y un checklist pensados para **Linux** (nginx + php-fpm +
> Supervisor + Certbot). Este documento describe el equivalente **funcional en Windows**.

**Fecha de despliegue:** 2026-06-15 · **Realizado sobre:** Windows Server 2022 Standard (Evaluation), 16 GB RAM, 8 vCPU, nube OpenStack.

---

## 0. TL;DR — qué quedó montado

| Componente | En Linux (repo) | En este Windows |
|---|---|---|
| Servidor web / TLS | nginx + Certbot | **IIS 10** + URL Rewrite + ARR + win-acme |
| PHP runtime | php-fpm | **PHP 8.4 (FastCGI en IIS)** |
| Gestor de procesos | Supervisor | **NSSM** (4 servicios de Windows) |
| Base de datos | PostgreSQL (apt) | **PostgreSQL 18.3** (binarios zip + servicio) |
| Frontend | Next.js (Node) | **Next.js 16 standalone** (servicio NSSM `node`) |
| WebSocket | Reverb + Supervisor | **Reverb** (servicio NSSM) tras ARR |
| Scheduler | crontab | **`schedule:work`** (servicio NSSM) |
| Cola | queue:work + Supervisor | **`queue:work`** (servicio NSSM) |

**URL de producción (cuando el DNS apunte aquí):** `https://apoyoempresarial-comecyt.gob.mx`
**Estado de verificación local:** `app:deploy-check` ✅ verde · `GET /api/health` → **200** · login admin → **200** + cookie JWT.

---

## 1. Por qué NO se usó VM Ubuntu / WSL2 / Hyper-V

El plan original (habilitar Hyper-V o WSL2 y correr `bootstrap.sh` en Linux) **es imposible en este servidor**:

```
Manufacturer : OpenStack Foundation      → este Windows YA es una VM (guest)
HypervisorPresent : True
SecondLevelAddressTranslation (SLAT/EPT) : False   → sin virtualización anidada
VirtualizationFirmwareEnabled            : False
```

- **Hyper-V** requiere SLAT → no disponible en el guest.
- **WSL2** usa una utility-VM de Hyper-V por debajo → también requiere virtualización anidada.
- Habilitarla depende del **host OpenStack** (el proveedor), no de este Windows.

> Si en el futuro el proveedor habilita *nested virtualization*, el camino ideal sería WSL2 + Ubuntu + `bootstrap.sh` casi sin cambios. Mientras tanto, **stack nativo Windows** es la única ruta estable.

---

## 2. Versiones reales (lo que el código exige, NO lo que dice el README)

⚠️ Los badges del `README.md` están desactualizados. Lo que `composer.lock` / `package.json` exigen:

| Software | Versión instalada | Nota |
|---|---|---|
| **PHP** | **8.4.22** NTS x64 (VS17) | El README dice 8.2; `composer.lock` fija Symfony 8 → **requiere PHP ≥ 8.4** |
| Laravel | **13.1.1** | El README dice 11; el lock trae `laravel/framework ^13` |
| JWT | `php-open-source-saver/jwt-auth 2.9` | NO es `tymon/jwt-auth` |
| Next.js | 16.2.9 + React 19.2.4 | ✅ |
| PostgreSQL | **18.3** | ✅ |
| Node.js | 24.16.0 | preinstalado |
| Composer | 2.10.1 | |

Rutas en disco:

```
C:\php\                      PHP 8.4 + php.ini    (extension_dir = C:\php\ext)
C:\pgsql\                    binarios PostgreSQL 18
C:\pgsql\data\               cluster (datos)
C:\tools\bin\                nssm.exe, composer.bat
C:\tools\git\                MinGit
C:\tools\composer\           composer.phar
C:\comecyt\                  repositorio (raíz del proyecto)
C:\comecyt\apps\api\         backend Laravel
C:\comecyt\apps\web\         frontend Next.js
C:\comecyt\logs\             logs de los servicios NSSM
C:\comecyt\SECRETS_GENERADOS.txt   ← ⚠️ mover a gestor de secretos y BORRAR
```

`C:\php`, `C:\tools\bin`, `C:\tools\git\cmd` están en el **PATH de máquina**.

---

## 3. Arquitectura de red (un solo sitio IIS)

```
                Internet (443/tcp)
                       │
        ┌──────────────▼───────────────┐
        │   IIS 10  (sitio "comecyt")   │   binding: apoyoempresarial-comecyt.gob.mx:443 (SNI)
        │   raíz física: apps\api\public│   + redirect 80→443
        └──────────────┬───────────────┘
       URL Rewrite + ARR enruta por path:
   ┌───────────────────┼───────────────────────┬───────────────────────┐
   ▼                   ▼                        ▼                       ▼
/api,/up,/sanctum   archivo físico         /app/* (WebSocket)      todo lo demás
  → index.php       (/storage, assets)     → ARR a 127.0.0.1:8080  → ARR a 127.0.0.1:3000
  vía PHP FastCGI   servido por IIS         (Reverb)                (Next.js standalone)
  (C:\php\php-cgi)
        │
        ▼
  Laravel 13  ──►  PostgreSQL 18  (127.0.0.1:5432, base comecyt_prod)
```

El `web.config` con estas reglas vive en `apps\api\public\web.config`.

---

## 4. Servicios de Windows (reemplazo de Supervisor)

Creados con **NSSM** (`C:\tools\bin\nssm.exe`). Todos `Automatic` (arrancan en el reboot).

| Servicio | Comando | Puerto |
|---|---|---|
| `postgresql-18` | `pg_ctl` (servicio nativo PG) | 5432 |
| `comecyt-web` | `node …\.next\standalone\server.js` (PORT=3000) | 3000 |
| `comecyt-reverb` | `php artisan reverb:start --host=127.0.0.1 --port=8080` | 8080 |
| `comecyt-queue` | `php artisan queue:work --tries=3 --timeout=90` | — |
| `comecyt-scheduler` | `php artisan schedule:work` | — |
| `W3SVC` | IIS | 80/443 |

### Comandos de operación

```powershell
# Estado
Get-Service postgresql-18,comecyt-web,comecyt-reverb,comecyt-queue,comecyt-scheduler,W3SVC

# Reiniciar un servicio
Restart-Service comecyt-web

# Editar la definición de un servicio (args, env, etc.)
C:\tools\bin\nssm.exe edit comecyt-reverb     # GUI
C:\tools\bin\nssm.exe set comecyt-queue AppParameters "artisan queue:work --tries=5"

# Logs (rotan a 10 MB)
Get-Content C:\comecyt\logs\comecyt-web.out.log -Tail 50
Get-Content C:\comecyt\logs\comecyt-reverb.err.log -Tail 50
```

> ⚠️ Al crear servicios con NSSM, **fija los argumentos con `AppParameters`**, NO en la línea
> del `nssm install`. Si se pasan en el install, `node`/`php` arrancan sin argumentos (node entra
> en REPL, php espera stdin) y el puerto nunca abre. Este fue un error real durante el despliegue.

---

## 5. Variables de entorno y secretos

- Backend: `C:\comecyt\apps\api\.env` (ACL endurecida: solo `SYSTEM`, `Administrators` = F; `IIS_IUSRS` = R).
- Frontend: `C:\comecyt\apps\web\.env.local` (las `NEXT_PUBLIC_*` se **hornean en build-time**).
- Secretos generados durante el despliegue: `C:\comecyt\SECRETS_GENERADOS.txt`
  (DB_PASSWORD, HEALTH_TOKEN, REVERB_APP_SECRET, PG_SUPERUSER_PASSWORD, ADMIN_PASSWORD).
  **➜ Cópialos a un gestor de secretos institucional y BORRA ese archivo.**

### ⚠️ Trampa crítica: `config:cache` rompe `env()`

`app:deploy-check` y `HealthController` leen `env('JWT_SECRET')`, `env('HEALTH_TOKEN')`,
`env('DB_PASSWORD')`, etc. **directamente**. Si ejecutas `php artisan config:cache`
(como hace `deploy.sh`), Laravel **deja de cargar el `.env`** y esas llamadas devuelven vacío:

- `app:deploy-check` mostraría errores falsos
- `GET /api/health` devolvería **404** siempre

**Por eso aquí NO se cachea la config.** El deploy-check solo lo marca como *warning*, no error.
(Tampoco se puede `route:cache`: hay rutas con closures en `routes/api.php`.)
Si en el futuro quieres cachear config por rendimiento, primero exporta esos secretos como
**variables de entorno de máquina** para que `env()` siga encontrándolos.

---

## 6. Health check y deploy-check

```powershell
cd C:\comecyt\apps\api
C:\php\php.exe artisan app:deploy-check        # debe terminar exit 0 (verde)

# /api/health REQUIERE el header X-Health-Token = HEALTH_TOKEN del .env
$h = (Get-Content .env | Where-Object {$_ -match '^HEALTH_TOKEN='}) -replace '^HEALTH_TOKEN=',''
curl.exe -k -H "X-Health-Token: $h" https://apoyoempresarial-comecyt.gob.mx/api/health
# → 200 {"status":"ok","checks":{"database":{"ok":true},"cache":{"ok":true},"storage":{"ok":true}}}
```

> Sin el header `X-Health-Token`, `/api/health` responde **404 a propósito** (fail-secure). No es un error.

Las 4 *advertencias* esperadas del deploy-check en Windows (no son errores, NO bloquean):
`Config cacheada`, `Rutas cacheadas`, `Scheduler en crontab` (Windows usa NSSM), `.env permisos 600` (chequeo Unix).

---

## 7. TLS / HTTPS

**Estado actual:** certificado **autofirmado** para `apoyoempresarial-comecyt.gob.mx`, enlazado a 443 (SNI).
Para pruebas locales se añadió `127.0.0.1 apoyoempresarial-comecyt.gob.mx` al archivo `hosts`.

### Para producción (cuando el DNS público apunte a este servidor)

1. **Quitar** la línea de prueba del `hosts` (`C:\Windows\System32\drivers\etc\hosts`).
2. Confirmar que el dominio resuelve a la IP pública de este server y que 80/443 están abiertos
   en el *security group* de OpenStack.
3. Emitir el certificado real con **win-acme** (gratuito, equivalente a Certbot):
   ```powershell
   # Descargar win-acme: https://www.win-acme.com  →  C:\tools\win-acme\
   C:\tools\win-acme\wacs.exe
   #  → opción "N" (new certificate) → seleccionar el sitio IIS "comecyt"
   #  → valida por HTTP-01 (la regla de rewrite ya excluye /.well-known/acme-challenge)
   #  → win-acme instala el cert en el binding y crea una tarea programada de renovación automática
   ```
4. (Opcional) Eliminar el certificado autofirmado del store `Cert:\LocalMachine\My`.

---

## 8. ✅ Checklist de salida a producción (pendientes para el TIC)

- [ ] **Dominio real**: confirmar el nombre correcto. `apoyoempresarial_comecyt.gob.mx` (con guion
      bajo `_`) **es inválido** para DNS y TLS. Se asumió `apoyoempresarial-comecyt.gob.mx` (guion medio).
      Si el real es otro, actualizar: `.env` (APP_URL, NEXT_PUBLIC_APP_URL, CORS_ALLOWED_ORIGINS,
      COOKIE_DOMAIN), `apps\web\.env.local` (NEXT_PUBLIC_*), el binding de IIS, el cert, y
      **recompilar el frontend** (`npm run build`, las NEXT_PUBLIC_* se hornean).
- [ ] **DNS** apuntando a la IP pública + **win-acme** para cert Let's Encrypt real.
- [ ] **Email institucional**: quedó pendiente. Hoy `MAIL_HOST=127.0.0.1:2525` (no envía). Para que
      funcione recuperación de contraseña, poner SMTP real en `.env` (MAIL_HOST/PORT/USERNAME/PASSWORD)
      y `MAIL_FROM_ADDRESS`.
- [ ] **Licencia de Windows**: esta es edición **Evaluation** (caduca ~180 días y el server empieza a
      apagarse solo). Activar licencia Standard antes de producción.
- [ ] **Backups de PostgreSQL** (ver §9).
- [ ] Mover `SECRETS_GENERADOS.txt` a gestor de secretos y borrarlo.
- [ ] Cambiar la contraseña del admin tras el primer acceso (está en SECRETS_GENERADOS.txt).

---

## 9. Backups y mantenimiento

### Backup diario de la BD (Tarea Programada)

```powershell
# Crear carpeta de backups
New-Item -ItemType Directory C:\comecyt\backups -Force
# Script de backup: C:\comecyt\backups\backup.ps1
$env:PGPASSWORD = '<PG_SUPERUSER_PASSWORD>'
$stamp = Get-Date -Format 'yyyy-MM-dd_HHmm'
& C:\pgsql\bin\pg_dump.exe -h 127.0.0.1 -U postgres -Fc comecyt_prod -f "C:\comecyt\backups\comecyt_$stamp.dump"
# Registrar como tarea diaria 02:00:
schtasks /create /tn "COMECYT-Backup" /tr "powershell -File C:\comecyt\backups\backup.ps1" /sc daily /st 02:00 /ru SYSTEM
```

### Actualizar el código

```powershell
cd C:\comecyt
git pull
cd apps\api ; C:\php\php.exe C:\tools\composer\composer.phar install --no-dev --optimize-autoloader
C:\php\php.exe artisan migrate --force
cd ..\web ; & 'C:\Program Files\nodejs\npm.cmd' install --legacy-peer-deps ; & 'C:\Program Files\nodejs\npm.cmd' run build
# Recopiar estáticos del standalone (Next NO lo hace automáticamente):
Copy-Item .next\static\*  .next\standalone\.next\static\ -Recurse -Force
Copy-Item public\*        .next\standalone\public\        -Recurse -Force
Restart-Service comecyt-web,comecyt-reverb,comecyt-queue,comecyt-scheduler
iisreset
```

---

## 10. Errores reales encontrados durante el despliegue (no repetir)

| # | Síntoma | Causa | Solución |
|---|---|---|---|
| W1 | `php.exe` exit `0xC0000135` | Falta VC++ Redistributable 2015-2022 | Instalar `vc_redist.x64.exe` |
| W2 | `composer install` falla: "requires php >=8.4" | `composer.lock` trae Symfony 8 | Usar **PHP 8.4**, no 8.3 |
| W3 | `package:discover` falla: "bootstrap/cache must be present" | El ZIP no trae dirs gitignored | Crear `bootstrap\cache` y `storage\framework\*` |
| W4 | Servicio NSSM "Running" pero puerto cerrado | Args pasados en `nssm install` | Fijarlos con `nssm set … AppParameters` |
| W5 | IIS responde 500.19 (Win32 33) a todo | Secciones `httpErrors`/`webSocket` bloqueadas | `appcmd unlock config -section:…` |
| W6 | `/api/health` 404 con config cacheada | `config:cache` desactiva carga de `.env` | No cachear config (ver §5) |
| W7 | `php artisan tinker <archivo>` se cuelga | tinker entra al REPL esperando stdin | Para scripts, usar `php -r` o SQL directo, no tinker en background |
| W8 | Next standalone sin estilos/JS | `next build` no copia `.next/static` ni `public` | Copiarlos a `.next\standalone\` (ver §9) |
| W9 | **Toda** la API da 500 tras endurecer ACL del `.env` | Con `fastcgi.impersonate=1`, PHP corre como **IUSR**, que leía `.env` por **herencia**; `icacls /inheritance:r` se la quitó → Laravel no lee `APP_KEY` | Se puso `fastcgi.impersonate = 0` en `php.ini` (PHP corre como el identity del app pool) y se dio a `IIS APPPOOL\comecyt` permiso **explícito** (RX en el árbol, R en `.env`, M en `storage` y `bootstrap\cache`). **Lección:** si endureces el `.env` con `/inheritance:r`, da acceso explícito al identity que usa FastCGI. |

> **Permisos NTFS finales del backend** (`apps\api`): `IIS APPPOOL\comecyt` + `IIS_IUSRS` con `RX` en todo el árbol; `M` (modify) en `storage\` y `bootstrap\cache\`; `R` en `.env` (solo SYSTEM/Administrators full). `fastcgi.impersonate = 0`.

---

## 11. Smoke test rápido (post-reboot)

```powershell
$d='apoyoempresarial-comecyt.gob.mx'
Get-Service postgresql-18,comecyt-web,comecyt-reverb,comecyt-queue,comecyt-scheduler,W3SVC | Format-Table Name,Status
curl.exe -k -s -o NUL -w "login: %{http_code}\n" "https://$d/login"                          # 200
$h=(Get-Content C:\comecyt\apps\api\.env|?{$_ -match '^HEALTH_TOKEN='}) -replace '^HEALTH_TOKEN=',''
curl.exe -k -s -w "\nhealth: %{http_code}\n" -H "X-Health-Token: $h" "https://$d/api/health"  # 200 ok
```

---

## 12. Estado al cierre del despliegue inicial

**Completado y verificado:**
- ✅ Stack completo corriendo + `app:deploy-check` verde + login real (JWT) 200.
- ✅ Backups automáticos diarios (tarea `COMECYT-Backup-Diario`, 02:00, retención 14 días).
- ✅ Límite de subida IIS subido a 50 MB (`maxAllowedContentLength`).
- ✅ Claves VAPID generadas (push backend).
- ✅ Firewall Windows: regla `COMECYT-Web-In` (80/443 inbound).
- ✅ **2FA activado** en `admin@comecyt.gob.mx` (secreto/QR/recovery en `SECRETS_GENERADOS.txt` y `admin_2fa_qr.svg`). Login probado: `login` → `requires_2fa` → `2fa/challenge` → JWT.
- ✅ Repo bajo control de versiones local (git). `docs/WINDOWS_DEPLOYMENT.md` commiteado. **Falta `git push`** a GitHub (requiere PAT con acceso al repo).
- ✅ win-acme descargado en `C:\tools\win-acme`; script listo: `C:\comecyt\emitir-cert-letsencrypt.ps1`.

**Pendiente (no bloquea el server, depende de terceros):**
- ⏳ DNS público + IP pública (infra) → luego correr `emitir-cert-letsencrypt.ps1` para el cert real.
- ⏳ SMTP institucional (sin él no se envían correos de reset).

**WebSocket (Reverb) vía IIS + ARR:**
- ✅ **Resuelto y verificado** (ver §13). El upgrade `wss://apoyoempresarial-comecyt.gob.mx/app/<key>` conecta end-to-end. El fix fue `<webSocket enabled="true" />` en `web.config`.

> Nota: el cert autofirmado se agregó a `Cert:\LocalMachine\Root` para pruebas locales; win-acme lo reemplaza por el real en el binding.

---

## 13. WebSocket vía IIS + ARR (resuelto)

Cómo quedó configurado el tiempo real (Reverb) detrás de IIS, y el problema que costó resolver.

**Arquitectura:** el frontend (laravel-echo/pusher-js) conecta a `wss://apoyoempresarial-comecyt.gob.mx:443/app/<REVERB_APP_KEY>`. IIS recibe el `wss` en 443, la regla de URL Rewrite `^(app|apps)` lo reescribe a `http://127.0.0.1:8080/{R:0}` y **ARR** hace el proxy (con upgrade WebSocket) hacia el servicio `comecyt-reverb`.

**Síntoma:** la conexión fallaba con
`WebSocketException: The 'Connection' header value '' is invalid` — el handshake **101 Switching Protocols** volvía del proxy **sin** el header `Connection: Upgrade`, y el cliente lo rechazaba. Un `GET` normal a `/app/<key>` devolvía `500` de `ARR/3.0`.

**Aislamiento (importante para el próximo TIC):**
- Reverb **directo** `ws://127.0.0.1:8080/app/<key>?protocol=7` → respondía `pusher:connection_established`. ✅ → Reverb está bien; el fallo es de la capa IIS/ARR (no de Reverb).

**Causa raíz:** en `apps/api/public/web.config` estaba `<webSocket enabled="false" />`. Eso **desactiva el módulo WebSocket de IIS** para el sitio. Contra-intuitivamente, **ARR necesita ese módulo ACTIVO** para completar el upgrade 101 y propagar los headers `Upgrade`/`Connection`. Con el módulo apagado, ARR no completaba el handshake.

**Fix aplicado:**
```xml
<!-- apps/api/public/web.config -->
<webSocket enabled="true" />
```
Pre-requisitos que ya estaban: feature **WebSocket Protocol** instalada (`Web-WebSockets`), **ARR proxy habilitado** (`system.webServer/proxy enabled="true"`), y la regla de rewrite `^(app|apps)` → `http://127.0.0.1:8080/{R:0}`.

**Verificación:**
```powershell
# directo (debe responder primero):
#   ws://127.0.0.1:8080/app/<key>?protocol=7   -> pusher:connection_established
# por IIS/ARR (tras el fix):
#   wss://apoyoempresarial-comecyt.gob.mx/app/<key>?protocol=7  -> Open + pusher:connection_established
```
Resultado: `Open` + `{"event":"pusher:connection_established",...}` ✅

> Las `NEXT_PUBLIC_REVERB_*` del frontend ya apuntan a `wss://apoyoempresarial-comecyt.gob.mx` puerto `443` esquema `https`, así que en cuanto el DNS+cert real estén activos, el tiempo real funciona sin recompilar.

| # | Síntoma | Causa | Solución |
|---|---|---|---|
| W10 | `wss://…/app` falla: `Connection header '' invalid`; ARR da 500 | `<webSocket enabled="false" />` apaga el módulo que ARR necesita para el upgrade 101 | Poner `<webSocket enabled="true" />` en `web.config` (con Web-WebSockets + ARR proxy activos) |

---

## 14. Procedimiento de actualización del código (futuro)

`C:\comecyt` **no es un clon** del repo (se montó desde un ZIP + `git init` local). Por eso para traer nuevos features del equipo dev **no se usa `git pull` directo ahí**. Método recomendado (seguro para un deployment vivo):

### Método A — clon temporal + copia selectiva (recomendado)
```powershell
# 0) Backup de seguridad ANTES de tocar nada
& C:\comecyt\backups\backup.ps1

# 1) Traer el código nuevo a un temporal
$t = 'C:\tools\_dl\update'
& 'C:\tools\git\cmd\git.exe' clone --depth 1 https://github.com/comecyt0/Desarrollo-Tecnol-gico.git $t

# 2) Copiar SOLO el código fuente, preservando estado/runtime/secretos
#    Backend (NO tocar: .env, storage\, vendor\, bootstrap\cache)
robocopy "$t\apps\api" C:\comecyt\apps\api /E /XD vendor storage bootstrap\cache node_modules /XF .env
#    Frontend (NO tocar: .env.local, node_modules, .next)
robocopy "$t\apps\web" C:\comecyt\apps\web /E /XD node_modules .next /XF .env.local

# 3) Reinstalar deps + migrar + rebuild
cd C:\comecyt\apps\api
& 'C:\php\php.exe' 'C:\tools\composer\composer.phar' install --no-dev --optimize-autoloader
& 'C:\php\php.exe' artisan migrate --force
cd C:\comecyt\apps\web
& 'C:\Program Files\nodejs\npm.cmd' install --legacy-peer-deps
& 'C:\Program Files\nodejs\npm.cmd' run build
Copy-Item .next\static\* .next\standalone\.next\static\ -Recurse -Force
Copy-Item public\*       .next\standalone\public\       -Recurse -Force

# 4) Reiniciar servicios + IIS
Restart-Service comecyt-web,comecyt-reverb,comecyt-queue,comecyt-scheduler
iisreset

# 5) Smoke test (§11)
```

### Método B — convertir `C:\comecyt` en clon real
Permite `git pull` futuro, pero hay que reconciliar la historia local (init) con upstream con cuidado (riesgo de sobrescribir `web.config`/runtime). Solo si se quiere flujo git puro. Requiere: `git remote add origin …`, `git fetch`, `git reset --mixed origin/main`, resolver los archivos locales (`web.config`, etc.) como cambios versionados.

### Método C — re-deploy en carpeta nueva + swap de IIS (zero-downtime)
Desplegar la versión nueva en `C:\comecyt2`, probarla en un puerto interno, y cambiar el *physical path* del sitio IIS + los `AppDirectory` de los servicios NSSM a la carpeta nueva. Más complejo, pero permite rollback instantáneo (volver a apuntar a la carpeta anterior). Recomendado cuando el sistema ya tenga tráfico real.

> Regla de oro: **siempre `backup.ps1` antes**, y nunca sobrescribir `.env`, `.env.local`, `storage\`, ni `bootstrap\cache\`.

---

_Documento generado durante el despliegue inicial en Windows. Mantener actualizado ante cambios de infraestructura._
