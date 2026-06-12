# 🔌 API Reference — COMECYT

> **Base URL (prod):** `https://dominio.gob.mx/api`
> **Base URL (dev):** `http://localhost:8000/api`
> **Authentication:** JWT en cookie HttpOnly `comecyt_auth` (automática vía `withCredentials: true`)
> **Content-Type:** `application/json` (excepto uploads)
> **Última actualización:** 2026-06-12

---

## Autenticación

El backend emite JWT en cookie `comecyt_auth` (HttpOnly, Secure en prod, SameSite=Strict, TTL 60min). El frontend usa `withCredentials: true` y la cookie viaja automáticamente. **No hay token en localStorage**.

`ReadJwtFromCookieMiddleware` la convierte en `Authorization: Bearer <token>` internamente.

### Headers comunes

| Header | Cuándo | Valor |
|---|---|---|
| `Cookie: comecyt_auth=...` | Endpoints autenticados | Automático del navegador |
| `Content-Type: application/json` | POST/PUT/PATCH con body | Siempre que envíes JSON |
| `Content-Type: multipart/form-data` | Uploads de archivos | Cuando subes documentos |
| `X-Requested-With: XMLHttpRequest` | AJAX | Recomendado |
| `Accept: application/json` | Todo | Siempre |
| `X-Health-Token: <secret>` | Solo `/api/health` | Pre-shared key |

### Respuestas estándar

```json
// Éxito
{ "data": { ... } }                          // 200 OK
{ "data": { ... }, "message": "..." }        // 201 Created
{ "message": "Operación exitosa" }           // 200 OK sin data

// Error de validación
{                                            // 422 Unprocessable Entity
  "message": "The given data was invalid.",
  "errors": {
    "email": ["El correo no es válido."],
    "password": ["Mínimo 8 caracteres."]
  }
}

// Error de auth
{ "message": "Unauthenticated." }            // 401
{ "message": "This action is unauthorized." } // 403

// Rate limiting
{ "error": "rate_limited", "retry_after": 60 } // 429
// + Header Retry-After: 60

// No encontrado
{ "message": "Resource not found" }          // 404

// Error servidor
{ "message": "Server Error" }                // 500 (en prod no expone detalle)
```

---

## 🔓 Endpoints Públicos (sin auth)

### Auth

```
POST /auth/login
  Body: { email: string, password: string, otp?: string }
  Response 200: emite cookie comecyt_auth + { user, role, expires_in }
  Response 401: { message: "Credenciales inválidas" }
  Response 429: cuenta bloqueada temporalmente
  Rate limit: 5 intentos/min por IP, 10/15min por email

POST /auth/forgot-password
  Body: { email: string }
  Response 200: { message: "Si el email existe, recibirás un correo." }
  Rate limit: 3/min

POST /auth/reset-password
  Body: { token: string, email: string, password: string, password_confirmation: string }
  Response 200: { message: "Contraseña actualizada" }
  Rate limit: 5/min

POST /auth/2fa/challenge
  Body: { challenge_id: string, code: string }
  Response 200: emite cookie comecyt_auth tras validar OTP
  Rate limit: 5/min

POST /auth/solicitar-acceso
  Body: {
    nombre, email, password,
    empresa_nombre, empresa_datos: { rfc, tipo_persona, rol_supervision },
    contactos: [
      { rol: 'responsable', nombre, telefono, correo },
      { rol: 'legal', ... },         // opcional
      { rol: 'administrativo', ... }, // opcional
      { rol: 'tecnico', ... }         // opcional
    ],
    cargo, telefono, motivo,
    terminos_aceptados: true
  }
  Response 201: solicitud creada, queda pendiente de aprobación admin
  Rate limit: 3/5min
```

### Catálogos públicos

```
GET /catalogs/programa/{tipo_programa_id}              # info general del programa
GET /catalogs/programa/{tipo_programa_id}/campos       # campos dinámicos
GET /catalogs/programa/{tipo_programa_id}/documentos   # documentos requeridos
GET /catalogs/programa/{tipo_programa_id}/criterios    # criterios de evaluación
GET /catalogs/programa/{tipo_programa_id}/rubros       # rubros de presupuesto
GET /catalogs/programa/{tipo_programa_id}/etapas       # etapas del flujo
GET /catalogs/programa/{tipo_programa_id}/modalidades  # modalidades

GET /carousel/slides                  # slides activos del carrusel de login
GET /categorias-apoyo                 # categorías (Fomento, Talento, Otra, etc.)
```

### Health Check

```
GET /api/health
  Headers: X-Health-Token: <token>
  Response 200: {
    status: "ok",
    checks: { database: {ok: true}, cache: {ok: true}, storage: {ok: true} },
    time: "2026-06-12T..."
  }
  Response 503: { status: "degraded", ... }
  Response 404: si X-Health-Token no coincide (fail-secure)
  Rate limit: 60/min
```

---

## 🔐 Endpoints Autenticados

### Auth (cualquier usuario logueado)

```
POST /auth/logout                  # invalida JWT + borra cookie
POST /auth/refresh                 # rota el JWT (refresh token)
GET  /auth/me                      # info del usuario actual
PUT  /auth/change-password         # cambio voluntario de contraseña

# 2FA
POST /auth/2fa/setup               # genera secret + QR code
POST /auth/2fa/enable              # activa tras confirmar OTP
POST /auth/2fa/disable             # desactiva (requiere OTP actual)
POST /auth/2fa/recovery-codes      # regenera códigos de recuperación
```

### Notificaciones (cualquier rol)

```
GET  /mis-notificaciones                          # paginado por defecto 20
POST /mis-notificaciones/{id}/leer                # marcar una como leída
POST /mis-notificaciones/leer-todas               # marcar todas

# Push notifications (Web Push API)
POST /push/subscribe                              # registra browser endpoint
POST /push/unsubscribe                            # de-registra

# Preferencias de notificación
GET  /me/preferences
PUT  /me/preferences
```

---

## 👤 Endpoints por rol: Solicitante

> Middleware: `solicitante`

```
GET    /solicitudes                               # mis solicitudes
GET    /solicitudes/convocatorias-activas         # convocatorias publicadas vigentes
POST   /solicitudes                               # crear borrador
GET    /solicitudes/{id}                          # ver una mía
PUT    /solicitudes/{id}                          # editar (solo si estado=borrador|observada)
DELETE /solicitudes/{id}                          # eliminar (solo borrador)

POST   /solicitudes/{id}/enviar                   # borrador → enviada
POST   /solicitudes/{id}/reenviar                 # observada → enviada
POST   /solicitudes/{id}/cancelar                 # cancela (estados permitidos)

# Documentos adjuntos
POST   /solicitudes/{id}/documentos               # upload FormData multipart
DELETE /solicitudes/{id}/documentos/{doc_id}

# Datos bancarios (estado=ministracion)
PUT    /solicitudes/{id}/beneficiario             # CLABE, banco, titular

# Informe final
POST   /solicitudes/{id}/informe                  # upload PDF + resultados
```

---

## 🔍 Endpoints por rol: Revisor

> Middleware: `revisor`

```
GET  /revisor/stats                                # KPIs personales
GET  /revisor/solicitudes/pendientes               # estados: enviada | observada
GET  /revisor/solicitudes/completadas              # aprobadas por mí
GET  /revisor/solicitudes/observadas               # las que regresé
GET  /revisor/solicitudes/{id}                     # detalle completo

POST /revisor/solicitudes/{id}/aprobar             # enviada → en_evaluacion
POST /revisor/solicitudes/{id}/observar            # enviada → observada
  Body: { motivo: string, campos: string[] }
POST /revisor/solicitudes/{id}/aprobar-informe     # seguimiento → cerrada

# Informes
GET  /revisor/informes                             # informes pendientes
PUT  /revisor/informes/{id}                        # aprobar/rechazar
```

---

## 🎯 Endpoints por rol: Evaluador

> Middleware: `evaluador`

```
GET  /evaluador/stats                                          # KPIs
GET  /evaluador/asignaciones                                   # mis asignaciones
GET  /evaluador/asignaciones/{id}                              # detalle solicitud + rúbrica
PUT  /evaluador/asignaciones/{id}/iniciar-evaluacion           # asignado → evaluando
  Body: { carta_imparcialidad_aceptada: true }
POST /evaluador/asignaciones/{id}/dictamen                     # evaluando → concluido
  Body: {
    calificaciones: [
      { criterio_id: number, puntuacion: number, comentario: string },
      ...
    ],
    recomendacion: 'recomendado' | 'no_recomendado',
    comentario_general: string
  }
```

---

## 🛡️ Endpoints por rol: Admin

> Middleware: `admin` (rol_id=1)

### Dashboard

```
GET /admin/stats                                   # contadores globales
GET /admin/activity                                # feed de auditoría reciente
GET /admin/alerts                                  # alertas activas (paginado)
```

### Solicitudes (vista completa de todas)

```
GET    /admin/solicitudes                          # ?search=&estado=&per_page=N
GET    /admin/solicitudes/{id}                     # vista normal
GET    /admin/solicitudes/{id}/full                # con TODAS las relaciones eager-loaded
POST   /admin/solicitudes/{id}/seguimiento         # ministracion → seguimiento
POST   /admin/solicitudes/{id}/rechazar            # rechazar
  Body: { motivo?: string }
POST   /admin/solicitudes/{id}/cancelar            # cancelar
  Body: { motivo?: string }
POST   /admin/solicitudes/{id}/cerrar              # ministracion|seguimiento → cerrada
POST   /admin/solicitudes/{id}/generar-convenio    # genera PDF descargable

GET    /admin/reportes/excel                       # descarga Excel
```

### CRUD de catálogos editables

Todos los siguientes son `Route::apiResource(...)` con métodos `index, store, show, update, destroy`:

```
/admin/programas              # + nested: /campos /documentos /rubros /etapas /modalidades /criterios
/admin/convocatorias
/admin/usuarios               # ?search=&per_page=N
/admin/convenios
/admin/ministraciones
/admin/informes
/admin/empresas               # antes /admin/instituciones
/admin/lista-negra
/admin/notificaciones         # solo index y show
/admin/carousel               # CRUD del carrusel de login
/admin/categorias-apoyo       # Fomento, Talento, Otra
```

### Gestión de accesos

```
GET  /admin/solicitudes-acceso                          # postulaciones pendientes
POST /admin/solicitudes-acceso/{id}/aprobar             # crea User + Empresa + envía email
POST /admin/solicitudes-acceso/{id}/rechazar
  Body: { motivo: string }

GET  /admin/reset-requests                              # peticiones de reset
POST /admin/reset-requests/{id}/aprobar                 # envía email con link
POST /admin/reset-requests/{id}/rechazar
```

### Asignación de evaluadores

```
POST   /admin/asignaciones-evaluador
  Body: { solicitud_id: number, evaluador_id: number, fecha_limite: 'YYYY-MM-DD' }
DELETE /admin/asignaciones-evaluador/{id}               # solo si estado=asignado
```

### Auditoría

```
GET /admin/audit-log                  # ?from=YYYY-MM-DD&to=YYYY-MM-DD&user_id=&action=
```

### Documentos generados

```
GET /documentos/dictamen/{asignacion_id}         # PDF del dictamen
GET /documentos/convenio/{solicitud_id}           # PDF del convenio
```

### Reverb status (solo admin)

```
GET /admin/reverb/status                          # health del WebSocket server
```

---

## Estados y transiciones (cheat sheet)

### Solicitud

```
borrador → (enviar) → enviada → (aprobar revisor) → en_evaluacion → (concluir evaluación) → aprobada
                          ↑↓
                    (observar) ←→ observada → (reenviar)

aprobada → (generar convenio) → convenio → (subir convenio firmado) → ministracion
                                                                          ↓
                                            seguimiento ← (capturar bancos + pago) ← ministracion.estado=pagada
                                                ↓
                                            cerrada (informe aprobado por revisor)

rechazada (desde: enviada | en_evaluacion | aprobada — terminal)
cancelada (desde: borrador | enviada | observada | en_evaluacion — terminal)
```

### Asignación de evaluador

```
asignado → (iniciar-evaluacion) → evaluando → (saveDictamen) → concluido
```

### Ministración

```
pendiente → (admin valida docs) → revision → (admin autoriza) → autorizada → (pago realizado) → pagada
                                                                                              ↘
                                                                                              rechazada
```

### Informe

```
pendiente → (solicitante sube) → en_revision → (revisor aprueba) → aprobado
                                                  ↘
                                                  rechazado
```

---

## Códigos HTTP usados

| Código | Cuándo |
|---|---|
| 200 OK | GET/PUT exitoso |
| 201 Created | POST que crea recurso |
| 204 No Content | DELETE exitoso |
| 301 Moved Permanently | HTTP→HTTPS redirect |
| 400 Bad Request | Cuerpo mal formado |
| 401 Unauthorized | Sin JWT o JWT inválido |
| 403 Forbidden | Sin permisos para esa acción |
| 404 Not Found | Recurso no existe |
| 405 Method Not Allowed | Verbo no permitido |
| 419 Page Expired | Token CSRF expirado (no aplica en API JWT) |
| 422 Unprocessable Entity | Validación falló |
| 429 Too Many Requests | Rate limit |
| 500 Internal Server Error | Bug del servidor |
| 502 Bad Gateway | nginx no pudo contactar php-fpm o Next |
| 503 Service Unavailable | Mantenimiento (`php artisan down`) |

---

## Headers de seguridad emitidos por backend

`SecurityHeadersMiddleware` agrega a CADA respuesta:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Strict-Transport-Security: max-age=31536000; includeSubDomains  (solo HTTPS)
```

Headers de rate limiting (cuando aplica):
```
X-RateLimit-Limit: 300
X-RateLimit-Remaining: 287
X-RateLimit-Reset: 1718198400
```

Headers expuestos a CORS (para que el frontend pueda leerlos):
```
Access-Control-Expose-Headers: X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset, Retry-After
```

---

## CORS

En **producción** (`APP_ENV=production`):
- `Access-Control-Allow-Origin: <lista CSV explícita>` (de `CORS_ALLOWED_ORIGINS`)
- `Access-Control-Allow-Credentials: true`
- `Access-Control-Allow-Methods: *`
- `Access-Control-Allow-Headers: Content-Type, Authorization, Accept, X-Requested-With`

En **dev local**:
- Acepta `localhost:3000`, `127.0.0.1:3000` + patterns para puertos arbitrarios

`DeployCheck` falla si `CORS_ALLOWED_ORIGINS` contiene `*` o `localhost` en producción.

---

## Rate limiting (5 capas)

| Capa | Límite | Donde |
|---|---|---|
| 1. Global por IP | 300 req/min | `RateLimitMiddleware` |
| 2. Login por IP | 5 intentos/min | `AuthLoginRateLimitMiddleware` |
| 3. Login por email | 10 intentos → lockout 15min | `AuthLoginRateLimitMiddleware` |
| 4. Endpoint específico | varía | `throttle:N,M` por ruta |
| 5. Gateway anti-DDoS | 1000 IPs únicas/60s → lockdown 5min | `ApiGatewayMiddleware` |

Cuando excede:
- HTTP 429
- Header `Retry-After: <segundos>`
- Body: `{ "error": "rate_limited", "retry_after": 60 }`

---

## Versión de la API

Sin versionado explícito en URL. Cambios breaking se anuncian en `CHANGELOG.md` y se da deprecation window de 30 días mínimo.

---

> Para reportar bugs en la API: GitHub issues con label `api`.
