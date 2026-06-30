# Cierre de sesion — 2026-06-30

> **Periodo de la sesion:** 2026-06-25 → 2026-06-30
> **Version inicial:** 8.2.0 (desplegado en Windows Server 2022, esperando DNS)
> **Version final:** 8.2.3 (accesible por IP para pruebas internas, flujo completo operativo)
> **Repo:** `github.com/comecyt0/Desarrollo-Tecnol-gico` — PRs #20 al #23 mergeados en `main`

---

## Logros de la sesion

### Dominio institucional definitivo (PRs #20, #21, #22 — 2026-06-25)

- Dominio cambiado de `apoyoempresarial-comecyt.gob.mx` a `comecyt-sistemas.edomex.gob.mx`
- Archivos actualizados: `apps/api/.env`, `apps/web/.env.local`, `apps/api/public/web.config`,
  `emitir-cert-letsencrypt.ps1`, `C:\Windows\System32\drivers\etc\hosts`, todos los docs
- Next.js reconstruido con `NEXT_PUBLIC_*` baked al nuevo dominio
- IIS bindings movidos al nuevo dominio; bindings del anterior eliminados
- Regla `LocalhostRedirect` en web.config: `http://localhost` redirige al dominio real

### Certificado TLS autofirmado confiable (PR #22 — 2026-06-25)

- `New-SelfSignedCertificate` con SANs: `comecyt-sistemas.edomex.gob.mx` + `localhost`
- Hash: `2e676a4963e3f4b5836852df17c1c7eb88a684ad` (valido 2 anos)
- Registrado en `http.sys` via `netsh` para ambos hostnames en puerto 443
- Importado a `cert:\LocalMachine\Root` (Trusted Root CA) — candado sin warning en el servidor
- Documentado en `WINDOWS_DEPLOYMENT.md` §21

### Acceso por IP para pruebas internas (PR #23 — 2026-06-30)

**Problema 1 — Diseno roto en todos los clientes:**
- Causa: Next.js `output:"standalone"` no copia los assets estaticos automaticamente al hacer `npm run build`
- Fix: copiar `.next/static/` → `.next/standalone/.next/static/` y `public/` → `.next/standalone/public/`
- Documentado en `WINDOWS_DEPLOYMENT.md` §22.1 — debe repetirse en cada rebuild

**Problema 2 — Login fallaba desde equipos remotos:**
- Causa: `CORS_ALLOWED_ORIGINS`, `COOKIE_DOMAIN=.comecyt-sistemas.edomex.gob.mx` y `COOKIE_SECURE=true`
  bloqueaban peticiones HTTP desde la IP
- Fix en `apps/api/.env`:
  - `CORS_ALLOWED_ORIGINS` ampliado con `http://10.250.36.241`
  - `COOKIE_DOMAIN=null`, `COOKIE_SECURE=false`, `COOKIE_SAME_SITE=Lax`
- Verificado: preflight CORS retorna `Access-Control-Allow-Origin: http://10.250.36.241`
- Documentado en `WINDOWS_DEPLOYMENT.md` §22.2 con tabla de valores a restaurar
- Checklist de transicion al dominio documentado en `WINDOWS_DEPLOYMENT.md` §22.3

---

## Estado del sistema al cierre

| Componente | Estado | Detalle |
|---|---|---|
| Backend Laravel | Activo | PHP 8.4.22 vía FastCGI |
| Frontend Next.js | Activo | Standalone, assets copiados, diseno correcto |
| PostgreSQL 18.3 | Activo | BD `comecyt_prod`, backups diarios 02:00 |
| Reverb WebSocket | Activo | Puerto 8080, proxy via IIS ARR |
| Cola (queue:work) | Activo | NSSM service `comecyt-queue` |
| Scheduler | Activo | NSSM service `comecyt-scheduler` |
| IIS reverse-proxy | Activo | Sitio `comecyt`, puertos 80/443 |
| CORS por IP | Habilitado (temporal) | Revertir cuando DNS este activo (§22.3) |
| Cookies HTTP | Habilitadas (temporal) | Revertir cuando DNS este activo (§22.3) |
| Acceso del equipo | Operativo | `http://10.250.36.241/login` — flujo completo |

---

## Ruta de instalacion del sistema (informacion clave)

El sistema esta desplegado en:

```
Ruta de la aplicacion:  F:\Desarrollo Tecnologico\
  apps/api/             Laravel (FastCGI, IIS)
  apps/web/             Next.js standalone
  docs/                 Documentacion operativa (copia local)
  backups/              Backups diarios de PostgreSQL
  emitir-cert-letsencrypt.ps1

Upstream (GitHub sync):  C:\tools\_dl\upstream\
  docs/                 Fuente de verdad para docs (lo que se pushea a GitHub)

IIS physical path:      F:\Desarrollo Tecnologico\apps\api\public\
IP del servidor:        10.250.36.241
```

---

## PRs mergeados en esta sesion

| PR | Titulo | SHA |
|---|---|---|
| #20 | docs: v8.2.1 localhost access + WINDOWS_DEPLOYMENT s20 | `0a007c4` |
| #21 | domain rename comecyt-sistemas.edomex.gob.mx (all files) | `7d4ddf3` |
| #22 | docs: v8.2.2 dominio + cert autofirmado + WINDOWS_DEPLOYMENT s21 | (squash) |
| #23 | docs: v8.2.3 acceso por IP fix standalone static transicion DNS | `750ae34` |

---

## Pendientes para produccion publica

### Bloqueante: DNS (Infra/OpenStack)

```
Accion:    Crear registro A: comecyt-sistemas.edomex.gob.mx → IP publica del servidor
           Abrir puertos 80 y 443 en el security group de OpenStack
Una vez listo, seguir el checklist de WINDOWS_DEPLOYMENT.md §22.3
```

### Checklist post-DNS (WINDOWS_DEPLOYMENT.md §22.3)

```
[ ] Restaurar CORS_ALLOWED_ORIGINS en apps/api/.env (solo dominio HTTPS)
[ ] Restaurar COOKIE_DOMAIN, COOKIE_SECURE=true, COOKIE_SAME_SITE=Strict
[ ] Actualizar NEXT_PUBLIC_* en apps/web/.env.local al dominio HTTPS
[ ] npm run build + copiar .next/static y public/ a .next/standalone/
[ ] artisan config:clear + Restart-Service comecyt-web, comecyt-queue, comecyt-scheduler
[ ] Borrar linea del hosts: 127.0.0.1  comecyt-sistemas.edomex.gob.mx
[ ] Ejecutar F:\Desarrollo Tecnologico\emitir-cert-letsencrypt.ps1
[ ] Verificar https://comecyt-sistemas.edomex.gob.mx/login → candado verde
```

### Pendientes adicionales

```
[ ] SMTP: configurar MAIL_* en F:\Desarrollo Tecnologico\apps\api\.env
[ ] Usuarios: reemplazar cuentas de prueba por cuentas reales (admin/revisor/evaluador/solicitante)
[ ] Carrusel: subir imagenes desde /admin/carrusel
[ ] Admin 2FA: escanear QR desde la app autenticadora con la cuenta real de produccion
[ ] Logo Edomex: cuando se tenga la imagen oficial, habilitar NEXT_PUBLIC_EDOMEX_LOGO_URL
[ ] Aviso de privacidad: aprobacion de Unidad de Transparencia (apps/web/src/app/(legal)/privacidad/page.tsx)
[ ] Terminos: aprobacion del area juridica
```

---

## Credenciales de prueba (solo en servidor interno)

URL: `http://10.250.36.241/login` (pruebas) / `https://comecyt-sistemas.edomex.gob.mx/login` (produccion)

Las credenciales de los 4 roles (Administrador, Revisor, Evaluador, Solicitante) y los datos de
infraestructura (PostgreSQL, IIS, certificado TLS) estan en:

```
C:\Users\Administrator\Desktop\COMECYT_CREDENCIALES.txt
```

> No se incluyen en este documento por seguridad. El admin tiene 2FA activo — necesita app autenticadora.
> Para pruebas sin 2FA usar los roles Revisor, Evaluador o Solicitante.

---

## Comandos rapidos del servidor

```powershell
# Estado de servicios
Get-Service comecyt-web, comecyt-reverb, comecyt-queue, comecyt-scheduler

# Reiniciar todos
Restart-Service comecyt-web, comecyt-reverb, comecyt-queue, comecyt-scheduler

# Logs del API
Get-Content "F:\Desarrollo Tecnologico\apps\api\storage\logs\laravel.log" -Tail 50

# Logs IIS
Get-Content C:\inetpub\logs\LogFiles\W3SVC2\u_ex*.log -Tail 30

# Backup manual
& "F:\Desarrollo Tecnologico\backup.ps1"

# Despues de cualquier rebuild de Next.js (OBLIGATORIO)
$web = "F:\Desarrollo Tecnologico\apps\web"
Copy-Item "$web\.next\static" "$web\.next\standalone\.next\static" -Recurse -Force
Copy-Item "$web\public\*"     "$web\.next\standalone\public\"       -Recurse -Force
Restart-Service comecyt-web
```

---

## Referencias

| Necesidad | Archivo |
|---|---|
| Que queda pendiente | `docs/NEXT_STEPS.md` |
| Operar dia a dia | `docs/OPERATIONS.md` |
| Cambios por version | `docs/CHANGELOG.md` |
| Despliegue Windows completo | `docs/WINDOWS_DEPLOYMENT.md` §1-§22 |
| Transicion al dominio | `docs/WINDOWS_DEPLOYMENT.md` §22.3 |
| Atender incidente | `docs/security/incident-response.md` |
| Endpoints del API | `docs/API.md` |

---

> **Sesion cerrada.** Sistema operativo en `http://10.250.36.241/login` con flujo completo (login, navegacion por roles, WebSocket). La apertura publica al dominio depende del DNS de Infra/OpenStack. Todo el proceso de transicion esta documentado en `WINDOWS_DEPLOYMENT.md §22.3`.
