# 💻 Guía de Desarrollo Local — COMECYT

> **Audiencia:** Desarrolladores que van a contribuir al código
> **Para deploy en prod:** ver [`DEPLOYMENT.md`](DEPLOYMENT.md)
> **Última revisión:** 2026-06-12

---

## 1. Setup inicial (one-time)

### 1.1 Requisitos en tu máquina

| Tool | Versión | Cómo instalar en Mac (brew) |
|---|---|---|
| PHP | 8.2+ | `brew install php@8.2` |
| Composer | 2.x | `brew install composer` |
| Node.js | 20 LTS | `brew install node@20` |
| PostgreSQL | 18 | `brew install postgresql@18 && brew services start postgresql@18` |
| Git | 2.40+ | `brew install git` |
| (Opcional) GitHub CLI | latest | `brew install gh` |

En Linux:
```bash
sudo apt update && sudo apt install -y \
    php8.2-cli php8.2-fpm php8.2-pgsql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-zip php8.2-bcmath php8.2-gd php8.2-intl php8.2-sodium \
    composer postgresql-18 nodejs npm git
```

Verifica:
```bash
php -v        # debe ser 8.2+
composer -V
node -v       # debe ser 20.x
psql -V
```

### 1.2 Clone del repo

```bash
git clone https://github.com/comecyt0/Desarrollo-Tecnol-gico.git comecyt
cd comecyt
```

### 1.3 Base de datos local

```bash
sudo -u postgres psql <<'SQL'
CREATE USER comecyt WITH PASSWORD 'comecyt_dev_password';
CREATE DATABASE comecyt_dev OWNER comecyt ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE comecyt_dev TO comecyt;
ALTER USER comecyt CREATEDB;
SQL
```

### 1.4 Configurar `.env` del backend

```bash
cd apps/api
cp .env.example .env
```

Edita `.env` con valores dev (suficiente para empezar):

```env
APP_NAME="COMECYT Dev"
APP_ENV=local                  # ← local, no production
APP_DEBUG=true                 # ← OK en dev
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=comecyt_dev
DB_USERNAME=comecyt
DB_PASSWORD=comecyt_dev_password

MAIL_MAILER=log                # ← escribe emails a storage/logs en vez de enviar

CORS_ALLOWED_ORIGINS=http://localhost:3000,http://127.0.0.1:3000
NEXT_PUBLIC_APP_URL=http://localhost:3000

HASH_DRIVER=argon2id

# Generar después con artisan:
APP_KEY=
JWT_SECRET=
```

### 1.5 Instalar dependencias

```bash
cd apps/api
composer install
php artisan key:generate
php artisan jwt:secret
php artisan storage:link
```

```bash
cd ../web
cp .env.example .env.local
# Editar:
# NEXT_PUBLIC_API_URL=http://localhost:8000/api
# NEXT_PUBLIC_APP_URL=http://localhost:3000
npm install --legacy-peer-deps
```

### 1.6 Correr migraciones + seeders

```bash
cd apps/api
php artisan migrate --seed
```

Esto crea:
- Tablas + índices
- Roles (admin, revisor, evaluador, solicitante)
- COMECYT como empresa institucional
- Admin: `admin@comecyt.gob.mx` / `password123`
- Catálogos básicos (municipios, bancos, áreas de conocimiento)
- `UsuariosPruebaSeeder` (porque `APP_ENV=local`):
  - Revisor: `asd@asd.com` / `password123`
  - Evaluador: `evaluadorr@uaemex.mx` / `password123`
  - Solicitante: `solicitante@institucion.mx` / `password123`

### 1.7 Arrancar servidores

**Opción A — 2 terminales separadas (recomendado):**

```bash
# Terminal 1 — Backend
cd apps/api
php artisan serve                  # → http://localhost:8000
```

```bash
# Terminal 2 — Frontend
cd apps/web
npm run dev                        # → http://localhost:3000
```

**Opción B — En background con `&`:**

```bash
cd apps/api && php artisan serve &
cd apps/web && npm run dev &
```

### 1.8 Verificar

Abre `http://localhost:3000` → login con `admin@comecyt.gob.mx` / `password123`.

---

## 2. Comandos de desarrollo más usados

### Backend (Laravel)

```bash
cd apps/api

# Servidor
php artisan serve                      # arranca PHP-served devserver

# Migraciones
php artisan migrate                    # corre las pendientes
php artisan migrate:rollback           # deshace la última
php artisan migrate:fresh --seed       # ⚠️ borra TODOS los datos y reseеда
php artisan migrate:status             # ver cuáles han corrido

# Seeders
php artisan db:seed                    # corre DatabaseSeeder
php artisan db:seed --class=UsuariosPruebaSeeder

# Caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Generar boilerplate
php artisan make:model Foo -mfsc       # modelo + migration + factory + seeder + controller
php artisan make:controller FooController --api
php artisan make:middleware FooMiddleware

# Tinker (REPL)
php artisan tinker
# Ejemplo:
# > User::where('email', 'admin@comecyt.gob.mx')->first()->roles

# Rutas registradas
php artisan route:list                 # todas
php artisan route:list --path=admin    # filtrar
php artisan route:list --name=login

# Validar
php -l app/Http/Controllers/Auth/AuthController.php   # syntax lint
./vendor/bin/pint                                      # formatter (Laravel Pint)
./vendor/bin/pint --test                              # solo check

# Tests
php artisan test                       # corre Pest/PHPUnit
php artisan test --filter LoginTest
```

### Frontend (Next.js)

```bash
cd apps/web

# Dev
npm run dev                            # arranca dev server con turbopack/webpack

# Build
npm run build                          # build producción (turbopack)
npx next build --webpack               # fallback si turbopack falla

# Lint
npm run lint                           # ESLint + plugin security
npm run lint -- --fix                  # auto-fix

# Tests
npx vitest run                         # corre tests
npx vitest                             # watch mode
npx vitest run --coverage              # con coverage

# Playwright (E2E)
npx playwright test
npx playwright test --ui               # UI mode

# Type check
npx tsc --noEmit
```

---

## 3. Workflow git

### 3.1 Branches

```
main                  # producción
  ├── feature/X       # features nuevas
  ├── fix/Y           # bug fixes
  ├── refactor/Z      # refactors
  ├── chore/W         # tareas de mantenimiento
  └── sec/V           # mejoras de seguridad
```

> Para hot-fixes en prod: rama desde `main`, fix, PR → merge directo. No `develop` ni Gitflow complicado.

### 3.2 Commits (Conventional Commits)

```
tipo(scope): descripción breve en imperativo

[cuerpo opcional con más detalle]

[footer opcional: Closes #123, Co-Authored-By, etc.]
```

**Tipos válidos:**
- `feat`: nueva funcionalidad
- `fix`: bug fix
- `sec`: mejora de seguridad
- `chore`: tarea no funcional (bump deps, cleanup)
- `docs`: solo cambios en documentación
- `refactor`: refactor sin cambios funcionales
- `test`: agregar/corregir tests
- `style`: formateo, sin cambios de lógica
- `perf`: mejora de performance

**Scopes comunes:**
- `api`, `web`, `db`, `ci`, `deps`, `auth`, `admin`, `solicitante`, `revisor`, `evaluador`

**Ejemplos buenos:**
```
feat(api): endpoint /admin/solicitudes/{id}/full con eager-loading completo
fix(web): SolicitudCard rompía al recibir empresa null
sec(api): rate-limit por email en /auth/login (lockout 15min)
chore(deps): bump axios 1.13 → 1.15 (CVE-2025-...)
docs: agregar troubleshooting de WebSocket
```

### 3.3 Pre-commit hooks

`.husky/pre-commit` corre:
- ESLint con `--fix` en `apps/web/**/*.{ts,tsx,js,jsx}`
- Laravel Pint en `apps/api/**/*.php`
- Block si hay errores

Para skip (uso excepcional):
```bash
git commit --no-verify -m "..."
```

### 3.4 Pull Request

1. Push branch:
   ```bash
   git push -u origin feature/X
   ```

2. Crear PR via UI o `gh`:
   ```bash
   gh pr create --title "feat(api): X" --body "..."
   ```

3. CI workflows que correrán:
   - `security-sast.yml`: Semgrep + Gitleaks + npm/composer audit + ESLint security
   - Build tests

4. Pedir review a al menos 1 dev. **Sin merge sin review** (incluso si CI pasa).

5. Squash & merge.

### 3.5 Hot-fix en producción

```bash
git checkout main && git pull
git checkout -b fix/critical-bug
# ... fix ...
git commit -m "fix(api): ..."
git push -u origin fix/critical-bug
gh pr create --title "fix(api): bug crítico" --body "..."
# revisar + merge
# después en el server: git pull origin main && bash deploy.sh
```

---

## 4. Estructura de código (convenciones)

### Backend (Laravel)

#### Estructura típica de un controller

```php
<?php

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
            // ... allowlist explícito
        ]);

        $item = ModeloRelevante::create($validated);
        return response()->json($item, 201);
    }
}
```

#### Reglas de oro

- **PascalCase** para classes, models, enums
- **camelCase** para métodos y variables
- **snake_case** para columnas DB y JSON keys
- Siempre `updateOrCreate` en seeders (idempotente)
- Middleware en **rutas**, NO en `__construct()` del controller (Laravel 11)
- `DB::beginTransaction()` para operaciones multi-modelo
- Mensajes vía `Message::*` enum + `ConfigHelper::msg()`

#### apiResource con plurales irregulares

**SIEMPRE** especificar `.parameters()` para plurales que terminen en `-ones`, `-as`, etc:

```php
// ❌ ROMPE silenciosamente
Route::apiResource('ministraciones', MinistracionController::class);

// ✅ CORRECTO
Route::apiResource('ministraciones', MinistracionController::class)
    ->parameters(['ministraciones' => 'ministracion']);
```

### Frontend (Next.js)

#### Estructura típica de un componente

```tsx
'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';
import type { Solicitud } from '@/types/api';

export default function MiPagina() {
  const [items, setItems] = useState<Solicitud[]>([]);  // tipo EXPLÍCITO
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const init = async () => {                           // patrón init async
      try {
        const { data } = await api.get('/endpoint');
        setItems(Array.isArray(data) ? data : (data?.data ?? []));  // ARRAY GUARD
      } catch (error) {
        console.error('Error cargando items:', error);
        setItems([]);
      } finally {
        setLoading(false);
      }
    };
    init();
  }, []);

  return <div>{/* ... */}</div>;
}
```

#### Reglas de oro

- `Array.isArray()` SIEMPRE antes de `.map()` / `.filter()` / `.length`
- Tipos explícitos: `useState<Solicitud[]>([])`, nunca `useState([])`
- `useEffect` con función `init` async interna
- Fechas con null-guard: `field ? new Date(field).toLocaleDateString('es-MX') : '—'`
- `dangerouslySetInnerHTML` con fallback: `{ __html: field ?? '' }`
- **Promise.all** para múltiples API calls síncronos (evita race conditions):
  ```tsx
  const [statsRes, listRes] = await Promise.all([
    api.get('/stats').catch(() => ({ data: {} })),
    api.get('/list').catch(() => ({ data: [] })),
  ]);
  ```

#### Imports

```tsx
import { ROLES } from '@/lib/roles';        // ✅ usar constants
// const ADMIN = 1;                         // ❌ nunca hardcodear
```

#### Layouts por rol

```
Admin       → AdminTopLayout   (NAV_GROUPS hardcoded en el componente)
Solicitante → RoleTopLayout    (5 nav items)
Revisor     → RoleTopLayout    (5 nav items)
Evaluador   → RoleTopLayout    (3 nav items)
```

Páginas nuevas en `/admin/*` deben agregarse manualmente al array `NAV_GROUPS` en `AdminTopLayout.tsx` — no aparecen automáticamente.

---

## 5. Debugging

### Backend

#### Ver queries SQL ejecutados

```php
DB::enableQueryLog();
// ... código ...
dd(DB::getQueryLog());
```

O en `AppServiceProvider::boot()`:
```php
if (app()->environment('local')) {
    DB::listen(fn($q) => Log::info($q->sql, $q->bindings));
}
```

#### Logs en vivo

```bash
tail -f apps/api/storage/logs/laravel.log
tail -f apps/api/storage/logs/security-$(date +%Y-%m-%d).log
```

#### Tinker interactivo

```bash
cd apps/api
php artisan tinker
> User::find(1)
> $s = Solicitud::with(['user', 'convocatoria.tipoPrograma.campos'])->first(); dd($s);
```

#### Xdebug (opcional)

```bash
# Mac
brew install php@8.2-xdebug
# Agregar a php.ini:
# zend_extension=xdebug
# xdebug.mode=debug
# xdebug.start_with_request=trigger
```

VSCode: instalar "PHP Debug" extension, configurar `launch.json`.

### Frontend

#### React DevTools

Chrome/Firefox extension. Imprescindible.

#### Sentry local

Para verificar el PII scrubbing sin enviar a Sentry real:
```bash
# en .env.local
NEXT_PUBLIC_SENTRY_DSN=https://fake@local.dev/0
```

#### Inspector de network

DevTools → Network. Filtrar por XHR/Fetch. Ver headers de respuesta (CSP, HSTS, etc.).

#### Bundle analyzer

```bash
cd apps/web
ANALYZE=true npm run build
# Abre report en navegador
```

---

## 6. Trabajar con WebSocket (Reverb)

```bash
# Arrancar Reverb
cd apps/api
php artisan reverb:start --host=127.0.0.1 --port=8080

# Test desde JS (en consola del navegador):
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;
const echo = new Echo({
  broadcaster: 'reverb',
  key: 'local-key-change-in-prod',
  wsHost: '127.0.0.1',
  wsPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws'],
});
echo.channel('test').listen('TestEvent', (e) => console.log(e));
```

---

## 7. Workflow con la BD

### Crear una migration

```bash
cd apps/api
php artisan make:migration add_columna_a_solicitudes
```

Edita `database/migrations/YYYY_MM_DD_HHMMSS_*.php`:
```php
public function up(): void {
    Schema::table('solicitudes', function (Blueprint $table) {
        $table->string('nueva_columna')->nullable()->after('estado');
    });
}

public function down(): void {
    Schema::table('solicitudes', function (Blueprint $table) {
        $table->dropColumn('nueva_columna');
    });
}
```

Corre:
```bash
php artisan migrate
```

### Modificar BD directamente (NO en prod)

```bash
psql comecyt_dev
# ... queries ...
```

### Reset completo (DEV)

```bash
php artisan migrate:fresh --seed
```

⚠️ Borra TODOS los datos. **Jamás** en producción.

---

## 8. Trabajar con uploads

### Backend

```php
// En controller
public function store(Request $request)
{
    $request->validate(['archivo' => 'required|file|mimes:pdf|max:5120']);

    $file = $request->file('archivo');
    $filename = Str::random(40).'.pdf';

    Storage::disk('public')->putFileAs(  // ← disk explícito
        "documentos/{$solicitud->id}",
        $file,
        $filename
    );

    return Storage::disk('public')->url("documentos/{$solicitud->id}/{$filename}");
}
```

### Frontend

```tsx
const handleUpload = async (file: File) => {
  const formData = new FormData();
  formData.append('archivo', file);

  const res = await api.post(`/solicitudes/${id}/documentos`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  });

  return res.data;
};
```

---

## 9. Variables de entorno y secretos

### En dev local

`.env` y `.env.local` están en `.gitignore` — nunca commitear.

Para compartir defaults entre devs: `.env.example` (commiteado).

### Si necesitas un secret nuevo

1. Agregarlo a `.env.example` con un placeholder
2. Documentar en este archivo (sección 1.4)
3. Mandar el valor real al equipo por canal seguro (NO Slack público)

---

## 10. Solución de problemas comunes (dev)

### 10.1 "Class 'X' not found" tras crear modelo

```bash
composer dump-autoload
```

### 10.2 Cambios en `.env` no se reflejan

```bash
php artisan config:clear
# Si tienes config cacheada:
php artisan config:cache
```

### 10.3 "could not connect to server: Connection refused" (psql)

```bash
brew services list                    # Mac
sudo systemctl status postgresql      # Linux

# Si está caído:
brew services start postgresql@18
sudo systemctl start postgresql
```

### 10.4 Next.js: "Hydration mismatch"

Causa: estado server-side y client-side difieren. Solución típica:
```tsx
const [val, setVal] = useState('');                // string vacío en server
useEffect(() => {
  setVal(Cookies.get('key') || '');                // setear en client
}, []);
```

O usar `suppressHydrationWarning` en el elemento específico.

### 10.5 "Permission denied" en `storage/`

```bash
cd apps/api
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage/framework storage/logs
# (en Mac no necesitas chown a www-data)
```

### 10.6 Tests fallan por DB

Tests usan SQLite por default (rápido). Si necesitas Postgres en tests:
```bash
# phpunit.xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE" value="comecyt_test"/>
```

### 10.7 ESLint security plugin marca regex como peligroso

Si tu regex es seguro (cuantificadores acotados), agrega comentario:
```ts
// eslint-disable-next-line security/detect-unsafe-regex -- bounded {1,64}; input controlado
const RE = /\b[A-Z]{1,64}@/g;
```

---

## 11. Hooks y skills útiles

### Pre-commit (ya configurado)

`.husky/pre-commit` corre:
- ESLint en archivos web modificados
- Pint en archivos API modificados

### Para correr el linter en TODO

```bash
cd apps/web && npm run lint
cd apps/api && ./vendor/bin/pint --test
```

---

## 12. Referencias

- `CLAUDE.md` — Reglas críticas y errores documentados
- `docs/ARCHITECTURE.md` — Decisiones de diseño
- `docs/DATABASE.md` — Schema BD
- `docs/API.md` — Endpoints
- `docs/CONTRIBUTING.md` — Cómo enviar PRs

---

> Para dudas: Issues en el repo o canal interno del equipo dev.
