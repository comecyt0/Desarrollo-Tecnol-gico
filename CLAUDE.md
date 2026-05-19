# CLAUDE.md — COMECYT Sistema
**Versión:** 8.0 (14 Abril 2026) | **Build:** 39 rutas, 0 errores | **PHP lint:** 0 errores

---

## Visión General del Proyecto

**COMECYT** (Consejo Mexiquense de Ciencia y Tecnología) es un sistema integral de gestión de apoyo científico que administra el ciclo completo de convocatorias de financiamiento:

```
Convocatoria → Solicitud → Revisión documental → Evaluación técnica
    → Convenio → Ministración → Informe final → Cierre
```

**Cuatro roles:**
| Rol | ID | Descripción |
|-----|----|-------------|
| Administrador | 1 | Control total del sistema |
| Revisor Documental | 2 | Valida documentación de solicitudes |
| Evaluador Técnico | 3 | Puntúa proyectos según criterios dinámicos |
| Solicitante | 4 | Institución que solicita financiamiento |

---

## Stack Tecnológico

| Capa | Tecnología |
|------|-----------|
| Frontend | Next.js 14 (App Router) + React 18 + TypeScript |
| Estilos | Tailwind CSS v4 + Shadcn UI v3 |
| Animaciones | Framer Motion |
| Backend | Laravel 11 (API-only) |
| Auth | JWT — `tymon/jwt-auth`, HS256, 24h TTL |
| Base de datos | PostgreSQL 18 |
| PDF | `barryvdh/laravel-dompdf` |
| Excel | `maatwebsite/laravel-excel` |
| Email (dev) | `MAIL_MAILER=log` — escribe en log, no envía |

**Puertos:** Frontend `localhost:3000` | Backend `localhost:8000` | API `localhost:8000/api`

---

## Arquitectura

### Estructura de carpetas

```
comecyt-system/
├── apps/
│   ├── api/                          # Laravel 11 API-only
│   │   ├── app/Http/Controllers/
│   │   │   ├── Admin/                # UserController, ConvenioController, CarouselController...
│   │   │   ├── Auth/                 # AuthController, PasswordResetController, RegistroAccesoController
│   │   │   ├── Catalogos/            # CatalogoController, ProgramaCatalogController
│   │   │   ├── Convocatorias/        # ConvocatoriaController
│   │   │   ├── Evaluaciones/         # EvaluadorController ← ÚNICO controller de evaluador
│   │   │   └── Solicitudes/          # SolicitudController, RevisionController
│   │   ├── app/Enums/Message.php     # 40+ mensajes centralizados
│   │   ├── app/Helpers/ConfigHelper.php
│   │   ├── config/comecyt.php        # Roles, estados, límites, montos
│   │   └── routes/api.php
│   └── web/                          # Next.js 14
│       └── src/
│           ├── app/
│           │   ├── (auth)/           # login, forgot-password, reset-password, solicitar-acceso
│           │   ├── admin/            # Todas las páginas admin
│           │   ├── evaluador/        # Páginas del evaluador
│           │   ├── revisor/          # Páginas del revisor
│           │   └── solicitante/      # Páginas del solicitante
│           ├── components/layout/
│           │   ├── AdminTopLayout.tsx   # Layout admin con mega-dropdown
│           │   └── RoleTopLayout.tsx    # Layout genérico para los 3 roles restantes
│           ├── lib/
│           │   ├── api.ts            # Axios singleton con JWT
│           │   └── roles.ts          # Constantes ROLES.ADMIN/REVISOR/EVALUADOR/SOLICITANTE
│           └── types/api.ts          # Interfaces TypeScript del dominio
```

### Layouts por Rol

```
Admin       → AdminTopLayout   (NAV_GROUPS hardcoded, 4 grupos, mega-dropdown 680px)
Solicitante → RoleTopLayout    (5 nav items, dropdown compacto 420px)
Revisor     → RoleTopLayout    (5 nav items)
Evaluador   → RoleTopLayout    (3 nav items)
```

**Regla:** Páginas nuevas en `/admin/*` deben agregarse **manualmente** al array `NAV_GROUPS` en `AdminTopLayout.tsx`. No aparecen automáticamente.

### Roles — IDs Centralizados

**Backend:** `config('comecyt.roles.X')` — nunca hardcodear números. Definido en `config/comecyt.php`.

**Frontend:** `ROLES.X` de `@/lib/roles` — nunca hardcodear números.
```typescript
import { ROLES } from '@/lib/roles';
// ROLES.ADMIN=1, ROLES.REVISOR=2, ROLES.EVALUADOR=3, ROLES.SOLICITANTE=4
```

### Mensajes y Configuración Centralizados (Backend)

```php
// Mensajes — app/Enums/Message.php
ConfigHelper::msg(Message::AUTH_INVALID_CREDENTIALS)

// Valores de config — config/comecyt.php
ConfigHelper::val('montos.monto_maximo_solicitud')   // → 60000
ConfigHelper::val('validation.titulo_proyecto_max_chars')  // → 255
config('comecyt.roles.admin')  // → 1
```

### Estados de Solicitud

```
borrador → enviada → en_evaluacion → aprobada → convenio → ministracion → seguimiento → cerrada
                ↕
            observada (ciclo revisor)
rechazada (desde: enviada | en_evaluacion | aprobada)
cancelada (desde: borrador | enviada | observada | en_evaluacion)
```

**Estados de AsignacionEvaluador:** `asignado` → `evaluando` → `concluido`
- `asignado`: recién asignado por admin
- `evaluando`: evaluador abrió la rúbrica (cambia via `PUT /evaluador/asignaciones/{id}/iniciar-evaluacion`)
- `concluido`: dictamen emitido

### Convocatoria → TipoPrograma (1:1 único)

Cada convocatoria tiene su propio `TipoPrograma` exclusivo. Los campos dinámicos, documentos, rubros y criterios de evaluación se configuran por convocatoria vía el Wizard de 7 pasos en `/admin/convocatorias/nueva`. Las convocatorias **deben** tener `tipo_programa_id` para que aparezcan al solicitante.

---

## Reglas Críticas (errores que ya costaron tiempo)

### 1. apiResource con plurales españoles en -ones/-es
Laravel pluraliza mal palabras que terminan en `-ones`, `-es`, `-as`. **Siempre** usar `.parameters()`:
```php
// ❌ ROMPE SILENCIOSAMENTE — genera {ministracione} como param
Route::apiResource('ministraciones', MinistracionController::class);

// ✅ CORRECTO
Route::apiResource('ministraciones', MinistracionController::class)
    ->parameters(['ministraciones' => 'ministracion']);
// Aplica a: ministraciones, instituciones, informes, convenios, solicitudes, usuarios
```

### 2. Cache + Eloquent Models — siempre .toArray()
```php
// ❌ Al deserializar del cache → __PHP_Incomplete_Class → TypeError en count()/property access
Cache::remember($key, $ttl, fn() => Model::with('rel')->get());

// ✅ CORRECTO
Cache::remember($key, $ttl, fn() => Model::with('rel')->get()->toArray());
```

### 3. Storage Disk explícito
```php
// ❌ Usa disco 'local' (privado) por defecto → archivo no accesible en /storage/
$file->storeAs("documentos/{$id}", $filename);

// ✅ CORRECTO — disco 'public' → accesible vía symlink public/storage
Storage::disk('public')->putFileAs("documentos/{$id}", $file, $filename);
Storage::disk('public')->url("documentos/{$id}/{$filename}");
```

### 4. Rutas públicas en middleware.ts (Next.js)
Páginas sin auth deben estar en `PUBLIC_PATHS` en `apps/web/src/middleware.ts`. Si no, el middleware las bloquea silenciosamente → el usuario ve que el botón "no hace nada".
```typescript
const PUBLIC_PATHS = ['/login', '/forgot-password', '/reset-password', '/solicitar-acceso'];
```

### 5. Password Reset desde Admin — limpiar token previo
`Password::sendResetLink()` retorna `passwords.throttled` si se llama >1 vez en 60 segundos. Siempre limpiar antes:
```php
DB::table('password_reset_tokens')->where('email', $email)->delete();
Password::sendResetLink(['email' => $email]);
```

### 6. Array validation en React — obligatorio
```typescript
// SIEMPRE antes de .map() / .filter() / .length
const items = Array.isArray(response.data)
  ? response.data
  : (response.data?.data ?? []);
```

### 7. Promise.all() para múltiples API calls
```typescript
// ❌ Race condition — stats y lista pueden no coincidir
api.get('/stats').then(...);
api.get('/list').then(...).finally(() => setLoading(false));

// ✅ CORRECTO — ambas cargan sincronizadas
const [statsRes, listRes] = await Promise.all([
  api.get('/stats').catch(() => ({ data: {} })),
  api.get('/list').catch(() => ({ data: [] })),
]);
setLoading(false);
```

### 8. Framer Motion Variants — 'as const'
```typescript
// ❌ TypeError: Type '"spring"' is not assignable
transition: { type: 'spring' }

// ✅ CORRECTO
transition: { type: 'spring' as const, damping: 20 }
```

### 9. Validación en 2 capas (Gold Rule)
Siempre validar en **frontend** (UX, bloquea antes de llamar API) **y** en **backend** (seguridad, rechaza con 422 si el frontend se saltea). Redundancia deliberada.

### 10. Flujo de recuperación de contraseña — es admin-mediated
No es un reset directo. El flujo es:
1. Usuario → `/forgot-password` → backend crea `PasswordResetRequest` (pending)
2. Admin aprueba en `/admin/reset-requests` → Laravel envía enlace al usuario

Esto es intencional: COMECYT requiere control institucional sobre quién puede resetear contraseñas.

### 11. UsuariosPruebaSeeder — NUNCA en producción
`DatabaseSeeder` ejecuta `UsuariosPruebaSeeder` solo si `app()->isLocal() || app()->environment('testing')`. Si se pasa a producción con `APP_ENV=production`, el seeder se omite automáticamente. Nunca remover este guard.

### 13. JWT — cookie HttpOnly `comecyt_auth` (desde v8.0)
El backend emite cookie `comecyt_auth` (HttpOnly, SameSite=Strict, Secure en producción). El frontend usa `withCredentials: true` — el navegador la envía automáticamente. `ReadJwtFromCookieMiddleware` la convierte en Bearer header. El frontend ya **no** guarda el token en `localStorage` ni en cookie JS.
```php
// Solo cookies NO sensibles se guardan en JS:
Cookies.set('userRole', roleSlug, ...);
Cookies.set('userName', user.name, ...);
// La cookie 'token' legacy persiste como fallback durante transición de sesiones
```

### 14. Scheduler de convocatorias — activar en producción
El comando `convocatorias:close-expired` corre cada hora vía scheduler. En producción agregar al crontab:
```
* * * * * cd /var/www/api && php artisan schedule:run >> /dev/null 2>&1
```

### 12. EvaluadorController — existe en un único namespace
`App\Http\Controllers\Evaluaciones\EvaluadorController` es el único controller de evaluador. **No existe** ni debe crearse `App\Http\Controllers\EvaluadorController`. El controller de Evaluaciones maneja: `asignaciones`, `show`, `startEvaluation`, `saveDictamen`, `asignar`, `desasignar`.

---

## Guía de Estilo

### PHP / Laravel

```php
// ✅ Estructura correcta de un controller
namespace App\Http\Controllers\Dominio;

use App\Http\Controllers\Controller;
use App\Models\ModeloRelevante;
use App\Helpers\ConfigHelper;
use App\Enums\Message;
use Illuminate\Http\Request;

class MiController extends Controller
{
    public function index()
    {
        $items = ModeloRelevante::with('relacion')->get();
        return response()->json($items);
    }

    public function store(Request $request)
    {
        $maxTitulo = ConfigHelper::val('validation.titulo_proyecto_max_chars');
        $validated = $request->validate([
            'titulo' => "required|string|max:{$maxTitulo}",
        ]);

        $item = ModeloRelevante::create($validated);
        return response()->json($item, 201);
    }
}
```

**Convenciones:**
- PascalCase: Classes, Models, Enums
- camelCase: métodos, variables, propiedades
- snake_case: columnas BD, parámetros de ruta, campos JSON
- Siempre `updateOrCreate` en seeders (idempotente)
- Middleware por grupo de rutas, nunca en `__construct()` (Laravel 11)
- Transacciones con `DB::beginTransaction()` para operaciones multi-modelo

### TypeScript / React

```typescript
'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import type { Solicitud } from '@/types/api';

export default function MiPagina() {
  const [items, setItems] = useState<Solicitud[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const init = async () => {
      try {
        const { data } = await api.get('/endpoint');
        setItems(Array.isArray(data) ? data : (data?.data ?? []));
      } catch (error) {
        console.error('Error cargando items:', error);
        setItems([]);
      } finally {
        setLoading(false);
      }
    };
    init();
  }, []);

  return <div>...</div>;
}
```

**Convenciones:**
- PascalCase: Components, Types, Interfaces
- camelCase: variables, funciones, props
- snake_case: campos de API (heredado del backend)
- Tipos explícitos: `useState<Solicitud[]>([])` nunca `useState([])`
- `Array.isArray()` obligatorio antes de cualquier `.map()` / `.filter()`
- `useEffect` con función `init` async interna
- Campos de fecha siempre con null-guard: `field ? new Date(field).toLocaleDateString('es-MX') : '—'`
- `dangerouslySetInnerHTML` siempre con fallback: `{ __html: field ?? '' }`

### Patrón de descarga de archivos (blob)
```typescript
const response = await api.get('/endpoint', { responseType: 'blob' });
const url = URL.createObjectURL(new Blob([response.data]));
const a = document.createElement('a');
a.href = url;
a.download = `nombre_${new Date().toISOString().split('T')[0]}.xlsx`;
a.click();
URL.revokeObjectURL(url);
```

---

## Usuarios de Prueba (solo local)

| Rol | Email | Contraseña |
|-----|-------|-----------|
| Admin | `admin@comecyt.gob.mx` | `password123` |
| Revisor | `asd@asd.com` | `password123` |
| Evaluador | `evaluadorr@uaemex.mx` | `password123` |
| Solicitante | `solicitante@institucion.mx` | `password123` |

> Creados por `UsuariosPruebaSeeder`, que solo corre con `APP_ENV=local`.

---

## Comandos Clave

```bash
# ── Backend (Laravel) ──────────────────────────────────────────
cd apps/api

php artisan serve                          # http://localhost:8000
php artisan migrate --seed                 # Migraciones + seed
php artisan migrate:fresh --seed           # Reset completo (¡borra datos!)
php artisan cache:clear
php artisan config:clear
php artisan route:list | grep nombre       # Verificar rutas
php -l app/Http/Controllers/X.php         # Lint sintaxis PHP
php artisan convocatorias:close-expired   # Cierre manual de convocatorias vencidas
php artisan schedule:work                  # Scheduler en producción (ejecutar como daemon)
php artisan app:deploy-check              # Verifica que el entorno de producción esté listo

# ── Deployment a producción ────────────────────────────────────
bash deploy.sh                             # Script completo: migrate, cache, crontab, permisos

# ── Frontend (Next.js) ─────────────────────────────────────────
cd apps/web

npm run dev                                # http://localhost:3000
npm run build                              # Build de producción (debe dar 0 errores)
npm run lint                               # ESLint

# ── Git ────────────────────────────────────────────────────────
git add apps/api/...  apps/web/...         # Staging selectivo
git commit -m "tipo: descripción breve"   # Conventional commits
```

---

## Variables de Entorno

### Backend `apps/api/.env`
```env
APP_ENV=local           # Controla si UsuariosPruebaSeeder corre
APP_KEY=base64:...
JWT_SECRET=...          # php artisan jwt:secret

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_DATABASE=comecyt_dev

MAIL_MAILER=log         # Dev: escribe en log. Prod: SMTP real
MAIL_FROM_ADDRESS="noreply@comecyt.gob.mx"

AUTH_LOGIN_RATE_LIMIT=5        # intentos/minuto en /auth/login
NEXT_PUBLIC_APP_URL=http://localhost:3000  # Para links en emails de reset
```

### Frontend `apps/web/.env.local`
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

---

## API Endpoints Completos

### Auth (público)
```
POST /auth/login
POST /auth/forgot-password     → crea PasswordResetRequest (requiere aprobación admin)
POST /auth/reset-password
POST /auth/solicitar-acceso    → crea SolicitudAcceso pendiente de aprobación admin
```

### Auth (protegido)
```
POST /auth/logout
POST /auth/refresh
GET  /auth/me
PUT  /auth/change-password
```

### Catálogos (público, sin auth)
```
GET /catalogs/programa/{id}
GET /catalogs/programa/{id}/campos
GET /catalogs/programa/{id}/documentos
GET /catalogs/programa/{id}/criterios
GET /catalogs/programa/{id}/rubros
GET /catalogs/programa/{id}/etapas
GET /catalogs/programa/{id}/modalidades
GET /carousel/slides            → slides activos para login page
```

### Solicitante
```
GET  /solicitudes                          → mis solicitudes
POST /solicitudes                          → crear borrador
GET  /solicitudes/convocatorias-activas
GET  /solicitudes/{id}
POST /solicitudes/{id}/enviar              → borrador → enviada
POST /solicitudes/{id}/reenviar            → observada → enviada
POST /solicitudes/{id}/informe             → subir informe final (FormData: archivo PDF + resultados)
POST /solicitudes/{id}/documentos          → subir documento adjunto
DELETE /solicitudes/{id}/documentos/{doc}
PUT  /solicitudes/{id}/beneficiario        → actualizar datos bancarios (CLABE, cuenta, titular)
GET  /mis-notificaciones
POST /mis-notificaciones/{id}/leer
POST /mis-notificaciones/leer-todas
```

### Revisor (middleware: revisor)
```
GET  /revisor/stats
GET  /revisor/solicitudes/pendientes       → estados: enviada | observada
GET  /revisor/solicitudes/completadas
GET  /revisor/solicitudes/observadas
GET  /revisor/solicitudes/{id}
POST /revisor/solicitudes/{id}/aprobar     → enviada → en_evaluacion
POST /revisor/solicitudes/{id}/observar    → enviada → observada
POST /revisor/solicitudes/{id}/aprobar-informe
GET  /revisor/informes
PUT  /revisor/informes/{id}
```

### Evaluador (middleware: evaluador)
```
GET  /evaluador/stats
GET  /evaluador/asignaciones
GET  /evaluador/asignaciones/{id}
PUT  /evaluador/asignaciones/{id}/iniciar-evaluacion  → asignado → evaluando
POST /evaluador/asignaciones/{id}/dictamen             → evaluando → concluido
```

### Admin (middleware: admin)

**Dashboard:**
```
GET /admin/stats
GET /admin/activity
GET /admin/alerts
```

**Solicitudes:**
```
GET  /admin/solicitudes                    → ?search=&estado=&per_page=N
POST /admin/solicitudes/{id}/seguimiento   → ministracion → seguimiento
POST /admin/solicitudes/{id}/rechazar      → acepta motivo opcional
POST /admin/solicitudes/{id}/cancelar      → acepta motivo opcional
POST /admin/solicitudes/{id}/cerrar        → ministracion|seguimiento → cerrada
POST /admin/solicitudes/{id}/generar-convenio
GET  /admin/reportes/excel
```

**CRUD completo:**
```
/admin/programas              + nested: /campos /documentos /rubros /etapas /modalidades /criterios
/admin/convocatorias
/admin/usuarios                → ?search=&per_page=N
/admin/convenios
/admin/ministraciones
/admin/informes
/admin/instituciones
/admin/lista-negra
/admin/notificaciones          → solo index y show
/admin/carousel                → CRUD slides del carrusel de login
```

**Gestión de accesos:**
```
GET  /admin/solicitudes-acceso
POST /admin/solicitudes-acceso/{id}/aprobar
POST /admin/solicitudes-acceso/{id}/rechazar
GET  /admin/reset-requests
POST /admin/reset-requests/{id}/aprobar
POST /admin/reset-requests/{id}/rechazar
```

**Asignación de evaluadores:**
```
POST   /admin/asignaciones-evaluador       → { solicitud_id, evaluador_id, fecha_limite }
DELETE /admin/asignaciones-evaluador/{id}
```

**Documentos:**
```
GET /documentos/dictamen/{id}
GET /documentos/convenio/{solicitud_id}
```

---

## Seguridad Implementada

| Área | Estado | Mecanismo |
|------|--------|-----------|
| Brute force login | ✅ | `AuthLoginRateLimitMiddleware` — 5 intentos/min |
| File upload seguro | ✅ | `ValidatesBinaryMimeTypes` trait — valida MIME real con `finfo_file()` |
| SQL injection | ✅ | Eloquent ORM, sin raw queries |
| XSS | ✅ | React escapa HTML por defecto |
| CSRF | ✅ | JWT stateless (sin cookies) |
| Code quality | ✅ | Pre-commit hooks: ESLint (frontend) + Laravel Pint (backend) |
| Middleware capas | ✅ | `ApiGatewayMiddleware` + `CircuitBreakerMiddleware` + `RateLimitMiddleware` global |
| Security headers | ✅ | `SecurityHeadersMiddleware` — X-Frame-Options, X-Content-Type-Options, X-XSS-Protection, HSTS (HTTPS only) |
| CORS headers | ✅ | `allowed_headers` lista explícita + `supports_credentials: true` en `config/cors.php` |
| Rate limiting endpoints públicos | ✅ | `throttle:3,1` en `forgot-password`, `throttle:3,5` en `solicitar-acceso` |
| Notificaciones — acceso propio | ✅ | `show()` en `NotificacionLogController` verifica `user_id === auth user id` |
| Eliminación de usuarios | ✅ | `UserController::destroy()` bloquea auto-eliminación y eliminación del último admin |
| JWT en HttpOnly cookie | ✅ | `AuthController::respondWithToken()` emite cookie `comecyt_auth` (HttpOnly, Secure en prod, SameSite=Strict). `ReadJwtFromCookieMiddleware` lo inyecta como Bearer. `api.ts` usa `withCredentials: true`. Frontend ya no usa `localStorage`. |
| Bloqueo de cuenta | ✅ | `AuthLoginRateLimitMiddleware` — 2 capas: IP (5/min) + email (10 intentos → 15 min lockout). Progresivo. |
| Cierre automático convocatorias | ✅ | Comando `convocatorias:close-expired` + scheduler horario en `routes/console.php` |

---

## Checklist de Producción

```bash
# 1. Ajustar .env en el servidor:
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
MAIL_MAILER=smtp   # + credenciales SMTP reales
NEXT_PUBLIC_APP_URL=https://tudominio.mx

# 2. Script de deploy (migrate, cache, crontab, permisos):
bash apps/api/deploy.sh

# 3. Verificar que todo esté correcto:
php artisan app:deploy-check

# 4. Supervisor para scheduler y queue workers:
sudo cp apps/api/resources/deploy/comecyt-scheduler.conf /etc/supervisor/conf.d/
sudo cp apps/api/resources/deploy/comecyt-queue.conf     /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

# 5. SSL:
sudo certbot --nginx -d tudominio.mx
```

---

## Pendiente (Deuda Técnica Aceptada)

| Ítem | Impacto | Solución |
|------|---------|---------|
| SMTP real de producción | Alto | Credenciales SMTP de comecyt.gob.mx. Ver `memory/pending_smtp_config.md` |
| Notificaciones bell en tiempo real | Bajo | Hoy carga al montar layout. Requiere polling o WebSockets |
| Página dedicada `/solicitante/informes` | Bajo | El upload está inline en detalle de solicitud |
| Imágenes del carrusel en producción | Medio | Seeder usa picsum.photos; admin sube imágenes reales vía `/admin/carrusel` |

---

## Errores Documentados (No Repetir)

### E1 — apiResource con plurales españoles [resuelto]
**Síntoma:** Controller no recibe el modelo (siempre null), update() retorna false, HTTP 200 con body vacío.
**Causa:** Laravel genera `{ministracione}` en lugar de `{ministracion}` para recursos que terminan en `-ones`.
**Fix:** `.parameters(['ministraciones' => 'ministracion'])` en todas las rutas apiResource con nombres irregulares.

### E2 — Cache + Eloquent Models [resuelto]
**Síntoma:** `TypeError: count() argument must be Countable|array, __PHP_Incomplete_Class given`.
**Causa:** Laravel serializa modelos Eloquent en cache; al deserializar el autoloader no tiene el contexto correcto.
**Fix:** Siempre `.toArray()` antes de `Cache::remember()`.

### E3 — Storage disk implícito [resuelto]
**Síntoma:** Archivo guardado correctamente pero URL retorna 403 Forbidden.
**Causa:** `$file->storeAs()` sin disco usa `'local'` (privado), pero `Storage::url()` lee de `'public'`.
**Fix:** Siempre `Storage::disk('public')->putFileAs()` y `Storage::disk('public')->url()`.

### E4 — EvaluadorController duplicado [resuelto 14-04-2026]
**Síntoma:** Estado de asignación inconsistente — `asignar()` ponía `'notificado'` pero `startEvaluation()` verificaba `'asignado'`, rompiendo el flujo.
**Causa:** Existían dos controllers: `App\Http\Controllers\EvaluadorController` (raíz, versión antigua) y `App\Http\Controllers\Evaluaciones\EvaluadorController` (versión completa). Las rutas admin usaban el antiguo para `asignar/desasignar` y el evaluador usaba el nuevo para el resto.
**Fix:** Métodos `asignar()` y `desasignar()` migrados al controller de Evaluaciones. Estado inicial cambiado a `'asignado'` (coherente con `startEvaluation`). Controller raíz eliminado.

### E5 — UsuariosPruebaSeeder en producción [resuelto 14-04-2026]
**Síntoma:** Potencial creación de usuarios con contraseñas públicas (`password123`) en producción.
**Causa:** `DatabaseSeeder` llamaba `UsuariosPruebaSeeder` incondicionalmente.
**Fix:** Guard con `app()->isLocal() || app()->environment('testing')`. En producción el seeder se omite.

### E6 — Middleware en constructores (Laravel 11) [resuelto]
**Síntoma:** `Call to undefined method middleware()` — error 500 en controllers admin.
**Causa:** Laravel 11 eliminó `$this->middleware()` en constructores.
**Fix:** Aplicar middleware en grupos de rutas (`'middleware' => 'admin'`), nunca en el constructor.

### E7 — Hydration mismatch (Next.js SSR + cookies)
**Síntoma:** "Hydration failed because the initial UI does not match what was rendered on the server".
**Causa:** `useState(cookieValue)` en servidor lee vacío; en cliente lee la cookie → valores distintos.
**Fix:** `useState('')` + `useEffect(() => { setValue(Cookies.get('key') || '') }, [])`.

### E8 — Alias de ruta evaluador eliminado erróneamente
**Síntoma:** Build roto — `asignaciones/page.tsx` importaba `../evaluaciones/page` que ya no existía.
**Causa:** Se eliminó `evaluaciones/` asumiendo que era el alias, sin leer el contenido real.
**Fix:** Siempre `cat archivo.tsx` antes de borrar. El archivo `evaluaciones/page.tsx` es ahora alias de `asignaciones/page.tsx`.
**Lección:** Nunca borrar archivos basándose en descripciones de terceros sin verificar el contenido real.

### E9 — `route('password.reset')` no existe en Laravel API-only
**Síntoma:** `adminApprove()` siempre fallaba con "No se pudo enviar el enlace".
**Causa:** Laravel API-only no registra rutas web, `route('password.reset')` lanza excepción capturada silenciosamente por el broker.
**Fix:** Construir URL manualmente: `env('NEXT_PUBLIC_APP_URL', 'http://localhost:3000') . '/reset-password?token=' . $token`.

### E10 — `User::evaluador()` apunta a modelo eliminado [resuelto 14-04-2026]
**Síntoma:** `evaluadorStats()` lanzaba `Class "App\Models\Evaluador" not found` en runtime.
**Causa:** `User.php` tenía `hasOne(Evaluador::class)` pero `Evaluador.php` fue eliminado en sesión anterior. `DashboardController::evaluadorStats()` llamaba `$user->evaluador->id`.
**Fix:** Eliminado `evaluador()` de `User.php`. `evaluadorStats()` ahora usa `$user->id` directamente contra `AsignacionEvaluador`.

### E11 — Estado `'en_revision'` inexistente en `adminStats()` [resuelto 14-04-2026]
**Síntoma:** El stat "Solicitudes en Revisión" contaba menos de lo real.
**Causa:** `whereIn('estado', ['enviada', 'en_revision', 'observada'])` — `'en_revision'` no es un estado válido del sistema.
**Fix:** Removido `'en_revision'` del whereIn. Los estados válidos para "en revisión" son `'enviada'` y `'observada'`.

### E12 — `CarouselSlideSeeder` no estaba conectado a `DatabaseSeeder` [resuelto 14-04-2026]
**Síntoma:** `php artisan migrate --seed` no poblaba la tabla `carousel_slides`.
**Causa:** El seeder existía pero nunca fue llamado desde `DatabaseSeeder`.
**Fix:** Agregado `$this->call(CarouselSlideSeeder::class)` al final de `DatabaseSeeder::run()`.

### E13 — Propiedad duplicada `tipo_programa` en `types/api.ts` [resuelto 14-04-2026]
**Síntoma:** Dos propiedades en `Convocatoria`: `tipoPrograma?` y `tipo_programa?` con el mismo tipo.
**Causa:** Artefacto de migración legacy — se agregó `tipo_programa` como alias snake_case sin eliminar el camelCase.
**Fix:** Eliminada `tipo_programa?: TipoPrograma`. Usar siempre `tipoPrograma` en TypeScript (la API retorna snake_case pero el objeto cargado via `with()` se accede como `tipoPrograma` por convenio).

### E14 — CORS + `withCredentials` — error silencioso
**Síntoma:** Cookie `comecyt_auth` no se envía a pesar de `withCredentials: true` en axios.
**Causa:** Si `config/cors.php` tiene `supports_credentials: false` o `allowed_origins: ['*']`, el navegador bloquea las credenciales silenciosamente.
**Fix:** `supports_credentials: true` + `allowed_origins` lista explícita (nunca `'*'` con credentials). Ambos deben estar activos.

### E15 — Scheduler sin cron en producción
**Síntoma:** `convocatorias:close-expired` nunca se ejecuta en producción.
**Causa:** Laravel Scheduler requiere que el cron del servidor llame `php artisan schedule:run` cada minuto, o que `php artisan schedule:work` corra como daemon.
**Fix:** Agregar al crontab del servidor: `* * * * * cd /path/to/api && php artisan schedule:run >> /dev/null 2>&1`

### E16 — Paginación: `data` cambia de array a objeto paginado
**Síntoma:** `items.map is not a function` al agregar `?per_page=N` a un endpoint que antes devolvía array.
**Causa:** Sin paginación la API retorna `[...]`, con paginación retorna `{ data: [...], total, ... }`. El componente espera array.
**Fix:** Siempre: `const items = Array.isArray(res.data) ? res.data : (res.data?.data ?? [])`. Los metadatos de paginación viven en `res.data.current_page`, `res.data.last_page`, etc.
