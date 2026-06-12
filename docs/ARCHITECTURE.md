# 🏗️ Arquitectura Técnica — COMECYT

> **Audiencia:** Desarrolladores nuevos, arquitectos, auditores
> **Profundidad:** capas, modelos, middleware, decisiones de diseño
> **Última revisión:** 2026-06-12

---

## 1. Visión general

```
┌─────────────────────────────────────────────────────────────────────┐
│                          USUARIO (Navegador)                         │
└────────────────────────────────┬────────────────────────────────────┘
                                 │ HTTPS
                                 ▼
┌─────────────────────────────────────────────────────────────────────┐
│   nginx (TLS termination + reverse-proxy + security headers)        │
└──────────────┬──────────────────────────────────────────────────────┘
               │
   ┌───────────┼────────────┬──────────────┬─────────────────┐
   │ /         │ /api/*     │ /storage/*   │ /app/* (WS)     │
   ▼           ▼            ▼              ▼                 │
┌────────┐ ┌──────────┐ ┌─────────┐  ┌─────────────┐         │
│ Next.js│ │ Laravel  │ │ Static  │  │ Reverb       │         │
│ Node.js│ │ php-fpm  │ │ files   │  │ WebSocket   │         │
│  :3000 │ │  :9000   │ │         │  │  :8080      │         │
└────┬───┘ └────┬─────┘ └─────────┘  └──────┬──────┘         │
     │         │                            │                 │
     │   Server-side fetch (`server-api.ts`)│                 │
     └─────────┤                            │                 │
               ▼                            │                 │
        ┌──────────────────┐                │                 │
        │   PostgreSQL 18  │◄───────────────┘                 │
        │   :5432          │                                  │
        └──────────────────┘                                  │
                                                              │
        ┌──────────────────┐                                  │
        │   Supervisor     │ ── Mantiene Reverb + Queue ──────┘
        └──────────────────┘
```

---

## 2. Stack tecnológico (detallado)

### Backend

| Componente | Versión | Por qué |
|---|---|---|
| PHP | 8.2+ (8.4 ideal) | Argon2id via sodium, readonly properties |
| Laravel | 11.x | API-only, Pennant, middleware chain |
| PostgreSQL | 18 | JSON queries, ILIKE search, robusto |
| `tymon/jwt-auth` | 2.x | JWT HS256, refresh tokens |
| `pragmarx/google2fa` | 8.x | 2FA TOTP (RFC 6238) |
| `barryvdh/laravel-dompdf` | 3.x | Generación PDF (convenios, dictámenes) |
| `maatwebsite/laravel-excel` | 3.x | Reportes Excel |
| `laravel/reverb` | 1.x | WebSocket server nativo Laravel |
| `minishlink/web-push` | 9.x | Web Push API |

### Frontend

| Componente | Versión | Por qué |
|---|---|---|
| Next.js | 16.x | App Router, server components, RSC |
| React | 19.x | Concurrent rendering, Suspense |
| TypeScript | 5.x | Tipos estrictos en todo el stack |
| Tailwind CSS | 4.x | `@variant dark`, OKLCH colors |
| Shadcn UI | 3.x | Componentes accesibles (Radix bajo) |
| Framer Motion | 12.x | Animaciones declarativas |
| `axios` | 1.x | HTTP client con interceptors |
| `js-cookie` | 3.x | Cookies no-sensibles (theme, locale) |
| `laravel-echo` + `pusher-js` | 1.x / 8.x | Cliente WebSocket |
| `@sentry/nextjs` | 10.x | Observabilidad con PII scrubbing |

---

## 3. Estructura del monorepo

```
comecyt/
├── apps/
│   ├── api/                                # Laravel 11 — backend
│   │   ├── app/
│   │   │   ├── Console/Commands/           # comandos artisan custom
│   │   │   │   ├── DeployCheck.php         # validación pre-deploy
│   │   │   │   ├── NotificarCierre.php     # alertas T-7, T-3, T-1
│   │   │   │   └── CloseExpired.php        # cierre auto convocatorias
│   │   │   ├── Enums/Message.php           # mensajes centralizados (40+)
│   │   │   ├── Events/                     # broadcast events
│   │   │   ├── Helpers/ConfigHelper.php    # acceso a config
│   │   │   ├── Http/
│   │   │   │   ├── Controllers/
│   │   │   │   │   ├── Admin/              # CRUD admin
│   │   │   │   │   ├── Auth/               # login, 2FA, reset
│   │   │   │   │   ├── Catalogos/          # public catalogs
│   │   │   │   │   ├── Convocatorias/
│   │   │   │   │   ├── Evaluaciones/       # ÚNICO controller evaluador
│   │   │   │   │   └── Solicitudes/
│   │   │   │   ├── Middleware/
│   │   │   │   │   ├── AdminMiddleware.php
│   │   │   │   │   ├── ApiGatewayMiddleware.php
│   │   │   │   │   ├── AuthLoginRateLimitMiddleware.php
│   │   │   │   │   ├── CircuitBreakerMiddleware.php
│   │   │   │   │   ├── EvaluadorMiddleware.php
│   │   │   │   │   ├── RateLimitMiddleware.php
│   │   │   │   │   ├── ReadJwtFromCookieMiddleware.php
│   │   │   │   │   ├── RevisorMiddleware.php
│   │   │   │   │   ├── SecurityHeadersMiddleware.php
│   │   │   │   │   └── SolicitanteMiddleware.php
│   │   │   │   └── Traits/
│   │   │   │       └── ValidatesBinaryMimeTypes.php
│   │   │   ├── Models/                     # 36 modelos Eloquent
│   │   │   ├── Notifications/              # email + database channels
│   │   │   └── Support/                    # helpers (PushSender, etc.)
│   │   ├── bootstrap/app.php               # registro middleware (Laravel 11)
│   │   ├── config/
│   │   │   ├── comecyt.php                 # config institucional
│   │   │   ├── cors.php                    # CORS estricto en prod
│   │   │   ├── jwt.php                     # JWT settings
│   │   │   └── hashing.php (implícito)     # Argon2id default
│   │   ├── database/
│   │   │   ├── migrations/                 # 59 migraciones
│   │   │   ├── seeders/                    # roles, COMECYT, admin, datos prueba
│   │   │   └── factories/                  # para tests
│   │   ├── routes/
│   │   │   ├── api.php                     # 126 endpoints
│   │   │   ├── console.php                 # scheduler
│   │   │   └── channels.php                # broadcasting auth
│   │   ├── storage/
│   │   │   ├── app/public/                 # uploads (vía /storage symlink)
│   │   │   └── logs/                       # laravel.log, security-*.log
│   │   └── deploy.sh                       # script idempotente de deploy
│   │
│   └── web/                                # Next.js 16 — frontend
│       ├── src/
│       │   ├── app/
│       │   │   ├── (auth)/                 # rutas no autenticadas
│       │   │   │   ├── login/
│       │   │   │   ├── forgot-password/
│       │   │   │   ├── reset-password/
│       │   │   │   └── solicitar-acceso/   # wizard 5 pasos
│       │   │   ├── admin/                  # páginas admin
│       │   │   ├── evaluador/
│       │   │   ├── revisor/
│       │   │   ├── solicitante/
│       │   │   └── layout.tsx
│       │   ├── components/
│       │   │   ├── admin/                  # ConvocatoriaPreviewPanel, etc.
│       │   │   ├── filters/                # SavedFiltersBar
│       │   │   ├── layout/
│       │   │   │   ├── AdminTopLayout.tsx  # nav admin (mega-dropdown)
│       │   │   │   └── RoleTopLayout.tsx   # nav otros roles
│       │   │   └── ui/                     # Shadcn + custom
│       │   │       ├── DataCardGrid.tsx    # grid genérico tipado
│       │   │       └── ...
│       │   ├── contexts/                   # ThemeContext, etc.
│       │   ├── hooks/                      # useSessionRefresh, useArrayApi
│       │   ├── i18n/                       # i18n provider (es, en)
│       │   ├── lib/
│       │   │   ├── api.ts                  # axios singleton + JWT cookie
│       │   │   ├── server-api.ts           # server-side fetch (RSC)
│       │   │   ├── roles.ts                # constantes ROLES.X
│       │   │   ├── institution.ts          # institution branding
│       │   │   ├── format.ts               # currency, dates
│       │   │   └── utils.ts                # cn helper
│       │   ├── middleware.ts               # auth gate de rutas
│       │   └── types/api.ts                # interfaces TS del dominio
│       ├── public/
│       │   ├── sw.js                       # service worker (PWA)
│       │   └── icons/                      # PWA icons
│       ├── next.config.ts                  # CSP, headers, images, output
│       ├── sentry.client.config.ts         # PII scrubbing client
│       ├── sentry.server.config.ts         # PII scrubbing server
│       └── eslint.config.mjs               # ESLint + security plugin
│
├── .github/
│   ├── dependabot.yml                      # actualizaciones semanales
│   └── workflows/
│       ├── security-sast.yml               # Semgrep + Gitleaks + audits
│       └── sbom-release.yml                # CycloneDX + SPDX por release
│
├── docs/                                   # ESTA carpeta
│   ├── README.md                           # índice
│   ├── DEPLOYMENT.md                       # guía despliegue
│   ├── OPERATIONS.md                       # manual operativo
│   ├── USER_GUIDE.md                       # por rol
│   ├── API.md                              # endpoints
│   ├── ARCHITECTURE.md                     # este archivo
│   ├── DATABASE.md                         # esquema BD
│   ├── DEVELOPMENT.md                      # dev local
│   ├── TESTING.md                          # tests
│   ├── CONTRIBUTING.md                     # cómo contribuir
│   ├── CHANGELOG.md                        # historial
│   ├── security/
│   │   ├── SECURITY.md                     # política
│   │   └── incident-response.md            # runbook IR
│   └── deploy-templates/                   # nginx, supervisor confs
│
├── CLAUDE.md                               # info del proyecto + lecciones
├── README.md                               # punto de entrada
└── deploy.sh                               # alias del script en apps/api
```

---

## 4. Cadena de middleware (Laravel 11)

Definida en `apps/api/bootstrap/app.php`:

```
Request entrante
    │
    ▼
1. TrustProxies                       # confía en 127.0.0.1 (nginx)
    │
    ▼
2. HandleCors                          # CORS estricto en prod
    │
    ▼
3. ValidatePostSize
    │
    ▼
4. TrimStrings + ConvertEmptyStringsToNull
    │
    ▼
5. SecurityHeadersMiddleware           # X-Frame, HSTS, etc.
    │
    ├──── Grupo 'api' (rutas /api/*):
    │       │
    │       ▼
    │     6a. ApiGatewayMiddleware     # detecta DDoS (1000 IPs/60s)
    │       │
    │       ▼
    │     6b. CircuitBreakerMiddleware # 5 fallos consecutivos → OPEN
    │       │
    │       ▼
    │     6c. RateLimitMiddleware      # 300 req/min/IP
    │       │
    │       ▼
    │     6d. ReadJwtFromCookieMiddleware  # cookie → Bearer header
    │       │
    │       ▼
    │     6e. SubstituteBindings        # route model binding
    │       │
    │       ▼ (si endpoint requiere)
    │     6f. auth:api                  # valida JWT
    │       │
    │       ▼ (si rol específico)
    │     6g. admin | revisor | evaluador | solicitante
    │
    ▼
Controller → Response
    │
    ▼
SecurityHeadersMiddleware (terminating)  # confirma headers de salida
```

### Middleware específicos de auth/login

```
POST /auth/login
    │
    ▼
AuthLoginRateLimitMiddleware
    │  ├── ¿IP > 5 intentos en 60s?   → 429
    │  └── ¿email > 10 intentos?       → lockout 15min
    │
    ▼
AuthController@login
    │
    ▼
2FA challenge si activado
```

---

## 5. Decisiones de diseño clave

### 5.1 JWT en cookie HttpOnly (no localStorage)

**Razón:** XSS (cualquier JS leaked) no puede leer `comecyt_auth` (HttpOnly). Token nunca se expone al JS del navegador.

**Trade-off:** requiere `withCredentials: true` + CORS estricto + cookies SameSite=Strict.

**Implementación:**
- `AuthController::respondWithToken()` emite cookie
- `ReadJwtFromCookieMiddleware` la convierte a Bearer header internamente
- Frontend solo necesita `withCredentials: true` en axios

### 5.2 Reset password mediado por admin

**Razón:** institución gubernamental requiere control. Evita account takeover via flow standard.

**Trade-off:** UX más lenta (espera aprobación).

### 5.3 Eloquent denylist sobre allowlist en `$guarded`

**Razón:** schema evoluciona — `$fillable` quedaría desactualizado tras cada migration y rompería seeders/controllers silenciosamente (Laravel ignora campos no fillable).

**Implementación:** `$guarded = ['id', 'created_at', 'updated_at']` + campos sensibles específicos (`estado`, `folio`, etc.). Defensa en profundidad: los controllers ya validan con `$request->validate()` (allowlist explícito).

### 5.4 Roles por ID (constants), no por enum/string

**Razón:** comparaciones rápidas, queries indexadas en `users.rol_id`.

**Implementación:**
- Backend: `config('comecyt.roles.admin')` → `1`
- Frontend: `ROLES.ADMIN` → `1`
- DB: FK `users.rol_id → roles.id`

### 5.5 Convocatoria 1:1 TipoPrograma

Cada convocatoria tiene su propio `TipoPrograma` exclusivo. Los campos dinámicos, documentos, rubros y criterios se configuran por convocatoria vía el wizard.

**Razón:** flexibilidad total — cada convocatoria puede pedir información completamente distinta sin tocar código.

**Implementación:** `convocatorias.tipo_programa_id` (FK NOT NULL), `tipo_programa.programa_campos/documentos/rubros/criterios_evaluacion`.

### 5.6 Cache + Eloquent → siempre `.toArray()`

```php
// ❌ MAL — al deserializar del cache → __PHP_Incomplete_Class → TypeError
Cache::remember($key, $ttl, fn() => Model::with('rel')->get());

// ✅ BIEN
Cache::remember($key, $ttl, fn() => Model::with('rel')->get()->toArray());
```

Documentado en CLAUDE.md como error E2.

### 5.7 Storage explícito disk

```php
// ❌ MAL — usa disco 'local' (privado) → URL 403
$file->storeAs("documentos/{$id}", $filename);

// ✅ BIEN
Storage::disk('public')->putFileAs("documentos/{$id}", $file, $filename);
Storage::disk('public')->url("documentos/{$id}/{$filename}");
```

### 5.8 PII scrubbing en Sentry

`beforeSend` redacta agresivamente antes de salir del navegador:
- `email`, `RFC`, `CURP`, `CLABE` (regex con cuantificadores acotados anti-ReDoS)
- `JWT tokens` (patrón `eyJ...`)
- `Bearer tokens`
- Keys conocidas: `password`, `secret`, `token`, etc.

Cumple LFPDPPP Art. 9 (transferencias internacionales).

### 5.9 No usar `apiResource` con plurales irregulares

Laravel pluraliza mal `ministraciones → ministracione`. **Siempre** `.parameters(['ministraciones' => 'ministracion'])`. Ver CLAUDE.md E1.

---

## 6. Flujos de datos críticos

### 6.1 Login con 2FA

```
1. POST /auth/login { email, password }
2. AuthController valida credenciales
   ├── ¿2FA activado?
   │     ├── NO: emite JWT → cookie comecyt_auth
   │     └── SÍ: genera challenge_id (TTL 5min) → response: { needs_2fa: true, challenge_id }
3. (si 2FA) Frontend muestra input de 6 dígitos
4. POST /auth/2fa/challenge { challenge_id, code }
5. AuthController valida OTP (Google2FA)
   ├── OK: emite JWT → cookie comecyt_auth
   └── Falla: incrementa contador, audit log, 401
```

### 6.2 Crear solicitud (solicitante)

```
1. Solicitante autenticado va a /solicitante/solicitudes/nueva
2. GET /solicitudes/convocatorias-activas → lista
3. Click convocatoria
4. GET /catalogs/programa/{tipo_programa_id}/{campos,documentos,rubros,criterios}
5. Frontend renderiza formulario dinámico
6. POST /solicitudes (estado=borrador)
7. Repetir PUT /solicitudes/{id} mientras llena
8. POST /solicitudes/{id}/documentos (multipart, FormData)
   ├── ValidatesBinaryMimeTypes valida MIME real con finfo
   ├── Generar nombre aleatorio (Str::random)
   └── Storage::disk('public')->putFileAs(...)
9. POST /solicitudes/{id}/enviar
   ├── Valida campos obligatorios completos
   ├── Valida documentos obligatorios subidos
   ├── Cambia estado borrador → enviada
   └── Dispatch evento → notificación a revisores
```

### 6.3 Evaluación de proyecto

```
1. Admin asigna evaluador:
   POST /admin/asignaciones-evaluador { solicitud_id, evaluador_id, fecha_limite }
   ├── INSERT asignaciones_evaluador (estado=asignado)
   └── Notifica evaluador (email + push)

2. Evaluador abre la rúbrica:
   PUT /evaluador/asignaciones/{id}/iniciar-evaluacion
     Body: { carta_imparcialidad_aceptada: true }
   └── Cambia estado: asignado → evaluando

3. Evaluador califica criterios:
   POST /evaluador/asignaciones/{id}/dictamen
     Body: { calificaciones: [...], recomendacion, comentario_general }
   ├── INSERT dictamen (1 por asignación)
   ├── INSERT solicitud_criterios_evaluacion (1 por criterio)
   ├── Calcula puntuación final ponderada
   ├── Cambia asignación: evaluando → concluido
   └── Si suficientes dictámenes → admin puede cerrar evaluación
```

### 6.4 WebSocket real-time

```
1. NotificacionLog::created event fires
2. Broadcast(new NotificacionCreada($n))->toOthers()
3. Reverb recibe en :8080
4. Cliente (laravel-echo) suscrito a channel('notif.user.{id}')
5. Frontend recibe payload, actualiza campanita

(Fallback: si Reverb down, polling cada 30s)
```

---

## 7. Caché y performance

| Capa | Tecnología | TTL | Qué cachea |
|---|---|---|---|
| Browser | `Cache-Control: public, immutable` | 30d (Next assets) | `/_next/static/*` |
| Browser | `Cache-Control: public` | 7d | `/storage/*` |
| nginx | gzip + http2 | n/a | Compresión |
| Next.js | RSC cache | n/a | Server components |
| Laravel | `Cache::remember` | 5min | Catálogos públicos (programa, campos, criterios) |
| PostgreSQL | shared_buffers | n/a | Queries comunes |

### Comandos para limpiar caché

```bash
# Backend
php artisan cache:clear
php artisan config:clear && php artisan config:cache
php artisan route:clear && php artisan route:cache
php artisan view:clear

# nginx
sudo nginx -s reload

# Frontend
rm -rf apps/web/.next  # full rebuild
```

---

## 8. Logs y observabilidad

### Logs estructurados

Laravel logs en JSON:
- `storage/logs/laravel-YYYY-MM-DD.log` — daily rotation 30 días
- `storage/logs/security-YYYY-MM-DD.log` — canal específico, 90 días

Eventos logueados a `security`:
- Login failed/locked
- 2FA failed
- Reset password requested/approved
- Admin actions (CRUD users, asignaciones, etc.)
- Mass-assignment intentos sospechosos

### AuditLog table

Tabla `audit_logs` (write-once, read-many):
- `user_id`, `action`, `subject_type`, `subject_id`
- `ip_address`, `user_agent`
- `metadata` (JSON)
- `created_at`

Inmutable por diseño (`$guarded = ['id']`, ningún controller expone update/delete).

### Sentry (opcional)

Si `SENTRY_DSN` está seteado:
- Errores no-controlados (5xx)
- Performance traces (10% por default)
- Sin PII (scrubbing agresivo en `sentry.{client,server}.config.ts`)

---

## 9. Escalamiento (notas para futuro)

### Vertical (más recursos al mismo server)
- RAM: subir php-fpm `pm.max_children` proporcional
- CPU: subir Reverb `--max-connections`
- Disk: separar `/var/www` y `/backup` en discos distintos

### Horizontal (múltiples servers)
**Cambios mínimos requeridos:**
1. **Sticky sessions** en load balancer (cookie `comecyt_auth`)
2. **Redis** centralizado para sesiones + cache
3. **PostgreSQL** primary + read replicas
4. **Storage en S3** (no en filesystem local) — cambiar `FILESYSTEM_DISK=s3`
5. **Reverb cluster** (requiere Redis pub/sub)
6. **Queue Redis** + workers en cada server

### Microservicios (no planeado por ahora)
El monorepo actual es deliberado — el dominio es lo suficientemente cohesivo. No fragmentar prematuramente.

---

## 10. Decisiones explícitamente rechazadas

| Tecnología | Por qué NO se eligió |
|---|---|
| **Sanctum** en lugar de JWT | Sanctum requiere CSRF tokens, complejidad extra para SPA cross-domain |
| **Vue / Inertia** | Stack ya estaba en Next.js, mejor ecosistema de UI |
| **Redis** como cache primario | Default en dev (no requiere infra extra); Redis es upgrade en prod |
| **SQLite** en prod | PostgreSQL para FK, JSON, ILIKE, robustez |
| **MongoDB** | Datos altamente relacionales (FK), no documentales |
| **Microservicios** | Dominio cohesivo, complejidad operativa innecesaria |
| **Kubernetes** | Contexto de servidor bare-metal institucional |
| **Docker Compose en prod** | Sin contenedores: nginx + supervisor + systemd suficiente |
| **Vercel/Netlify** | Datos PII deben quedar en infraestructura institucional (LFPDPPP) |

---

## 11. Referencias internas

- `CLAUDE.md` — Errores documentados con causa/síntoma/fix
- `docs/DATABASE.md` — Esquema BD detallado
- `docs/API.md` — Endpoints
- `docs/security/incident-response.md` — Runbook IR

---

> Cualquier cambio arquitectónico mayor debe documentarse aquí + ADR (Architecture Decision Record) en `docs/adr/`.
