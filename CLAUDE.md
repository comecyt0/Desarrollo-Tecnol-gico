# CLAUDE.md —# Memoria del Proyecto - Sistema COMECYT

## Documentación y Problemas Comunes (Troubleshooting)

### 1. Laravel 11: Rutas de API No Encontradas (404)
En Laravel 11, el archivo `routes/api.php` ya no se carga por defecto si la API no se inicializó con `php artisan install:api`. Para habilitarlo manualmente sin sobreescribir el archivo de rutas, asegúrate de que `bootstrap/app.php` contenga la llave `api:` dentro de `->withRouting()`:
```php
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php', // ¡Crucial para habilitar /api/* !
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
```

### 2. Tailwind CSS v4 vs Shadcn UI v3
Si los colores de Shadcn (como `bg-primary`) no funcionan y el estilo se ve transparente o blanco, es porque Tailwind v4 cambió la forma en la que lee las variables CSS. Para arreglarlo, debes mapear explícitamente las variables nativas HSL creadas por Shadcn adentro de la directiva `@theme` en tu `globals.css`:
```css
@theme {
  --color-primary: hsl(var(--primary));
  --color-primary-foreground: hsl(var(--primary-foreground));
  /* ... resto de variables ... */
}
```

### 3. Error Auth guard [api] is not defined
En Laravel 11, la librería `tymon/jwt-auth` o los middlewares protegidos por `auth:api` fallan por defecto ya que **Laravel 11 eliminó el 'guard api' de base**. Es obligatorio ir a `config/auth.php` y definir el `api` guard apuntando a la configuración correspondiente (ej. driver `jwt` o `sanctum`):
```php
    'guards' => [
        'web' => [ 'driver' => 'session', 'provider' => 'users' ],
        'api' => [ 'driver' => 'jwt', 'provider' => 'users' ], // <-- Añadir esto explicitamente
    ],
```

### 4. Next.js "Parallel Routes Error" y Redirecciones 404
Si obtienes el error visual de construcción `You ক্ষমতায়not have two parallel pages that resolve to the same path` al entrar al sistema, es porque múltiples "Route Groups" con paréntesis (ej. `(admin)`, `(solicitante)`) evalúan las mismas URLs (ej. `/dashboard`). 
**Solución:** Los "layouts" o vistas por rol deben ser carpetas estáticas literales sin paréntesis (`/admin/dashboard`, `/solicitante/dashboard`) para no chocar entre sí.

**Consecuencia IMPORTANTE (404s en Navegación):** 
1. Al convertir `(admin)` en carpeta real de ruta `/admin`, si rediriges al usuario a `/admin` y no existe un archivo `page.tsx` en la raíz de la carpeta base (solo existe en `admin/dashboard/page.tsx`), Next.js entregará un **Error 404**. Siempre redirige a la ruta absoluta terminal (ej. `router.push('/admin/dashboard')`).
2. Lo mismo ocurre con botones o links del Sidebar. Si en tu layout defines un href a `/admin/solicitudes` y no existe el archivo estricto `apps/web/src/app/admin/solicitudes/page.tsx`, la página arrojará un 404 duro nativo de Next.js. ¡Asegúrate de construir todas las páginas indexadas en tus Layout Headers/Sidebars!

### 5. Error 422 Unprocessable Entity (Axios)
Si al usar un formulario en React (como *Nueva Convocatoria*) obtienes un `AxiosError 422`, significa que Laravel bloqueó tu petición porque no pasaste la validación estricta de `Request`. 
**Solución:** Revisa siempre las reglas `$request->validate()` en tu Controller de Laravel (ej. `ConvocatoriaController`). Por ejemplo, Laravel en este proyecto exige el campo `'estado' => 'required|in:borrador,activa,cerrada'` de manera obligatoria. Siempre asegúrate de que el payload JSON del frontend `({...formData})` coincida con las llaves que exige el framework backend.

### 6. Imágenes rotas o no válidas en public/ (Turbopack)
Si al colocar una nueva imagen en `/apps/web/public/` el componente `<Image>` o `<img>` carga roto, o la terminal de Next.js dice `The requested resource isn't a valid image for /imagen.png received null`, es por el estricto cacheo de Turbopack.
**Solución:** Existen dos pasos importantes. 1) Evita usar nombres de archivo en mayúsculas (ej. `MARCA.png` -> `logo.png`) para evitar conflictos de *Case-Sensitivity* en el routing estático interno. 2) Obligatoriamente apaga el servidor (`Ctrl+C`) y vuelve a ejecutar `npm run dev` para que el cache de Turbopack regenere el árbol de assets de la carpeta pública.

### 7. Ciclo Infinito de Redirección (Bouncing) en Autenticación Next.js
- **Problema:** Al presionar "Iniciar sesión", el botón se quedaba en "Autenticando..." sin avanzar a pesar de que el API devolvía 200 OK y el usuario era correcto.
- **Causa:** Se cambió la implementación local de manejo de estado a `localStorage` puro eliminando `js-cookie`. Sin embargo, `middleware.ts` del App Router **sólo** puede leer cookies mediante `request.cookies.get('token')` porque no tiene acceso al `localStorage` o a las APIs del navegador (DOM). Al fallar la lectura de la cookie, el backend permitía el acceso pero el middleware frontend forzaba la intercepción asincrónica rebotando de nuevo a `/login` silenciosamente.
- **Solución:** Para que el guardián de rutas (`middleware.ts`) valide los accesos a los distintos dashboards (`/admin`, `/solicitante`), el JWT DEBE guardarse forzosamente como Cookie HTTP-compatible utilizando `Cookies.set('token', access_token, { expires: 1 })`. Adicionalmente, si el texto oscuro de un logo PNG se pierde en un fondo oscuro, usar Glassmorphism (`bg-white/95 backdrop-blur-md p-3 rounded-2xl`) en lugar de filtros CSS ayuda a integrarlo sin destruir los colores originales.

### 8. Botones Muertos en Tablas Shadcn UI (Falta de Router Push / Links)
- **Problema:** Los botones de "Ver" (`Eye`) o "Editar" (`Edit`) al final de las filas en las tablas del Panel de Administración aparentemente no hacían nada al recibir clics.
- **Causa:** Al mapear componentes puros `<Button>` en iteraciones de arreglos, si no se les programa un evento `onClick={}` o se les envuelve en un componente `<Link href={}>` nativo de Next.js, funcionan como simples cascarones visuales estáticos.
- **Solución:** Todo botón que implique navegación hacia una vista de detalles (ej. visualizar una Convocatoria) debe estar anidado estrictamente denro de `<Link href={"/ruta/" + id}>`. Nunca asumir que los iconos inician rutas por sí solos.

### 9. Sidebars apuntando a rutas inexistentes (Alias vs Carpetas)
- **Problema:** Al desarrollar las vistas por rol, los sidebars a veces colapsan con un `404` duro nativo de Next.js si una opción del menú apunta a un endpoint lógico que no tiene una carpeta física (ej. Sidebar apunta a `/evaluador/asignaciones` pero solo existe `/evaluador/evaluaciones`).
- **Solución:** Si conceptualmente dos rutas son la misma bandeja (ej. asignaciones vs evaluaciones), no es necesario duplicar el código. Crea la carpeta de la ruta alias (`/evaluador/asignaciones/page.tsx`) y exporta la vista original directamente: `export { default } from '../evaluaciones/page';`. Alternativamente, usa `redirect('/ruta-real')` si deseas cambiar explícitamente la URL en el navegador.

### 11. Sistema de Notificaciones Reales
- **Implementación:** Se creó la notificación `SolicitudEstadoActualizado` que utiliza los canales `mail` y `database`.
- **Integración:** Los controladores `SolicitudController`, `RevisionController` y `EvaluadorController` ahora disparan esta notificación automáticamente cuando hay un cambio de estado en la solicitud.
- **Configuración:** Utiliza `MAIL_MAILER=smtp` configurado para Mailpit en host local.

---

### 10. Dashboards Dinámicos (API Connection)
- **Problema:** Los dashboards mostraban datos estáticos (hardcoded) en el frontend.
- **Solución:** Se implementó `DashboardController` en el API y se registraron rutas específicas por rol: `/api/admin/stats`, `/api/revisor/stats`, `/api/evaluador/stats`. 
- **Nota:** El Admin Dashboard requiere que el componente del frontend mapee correctamente los iconos de Lucide (ej: `icon: 'Bookmark'`) que ahora vienen como string desde el JSON.

### 12. Gestor de Archivos (File Storage)
- **Implementación:** `DocumentoUploadController` se utiliza para recibir archivos nativos `PDF` (< 5MB) desde React.
- **Regla frontend:** Los botones en Next.js nunca deben ser simples `onClick` visuales para documentos; deben estar envueltos en `<label className="..."> <input type="file" onChange={handleFileUpload} /> </label>` usando `FormData` y `multipart/form-data`.
- **Regla backend:** El disco es el nativo público configurado mediante `php artisan storage:link`. La ruta de guardado es `/storage/documentos/{id}/archivo.pdf`.

### 13. CRUDs Administrativos con Modales Inline
- **Patrón:** Para las pantallas CRUD del admin (ej. Instituciones, Lista Negra) se usa el patrón de Modal Inline propio de React (`showModal` state + overlay) en lugar de `shadcn/ui Dialog` para mayor portabilidad.
- **Estado del formulario:** Cada modal maneja su propio `form` state con `useState(emptyForm)`. Al abrir para editar, se carga el objeto existente; al crear, se resetea a `emptyForm`.
- **Regla de roles:** La pantalla de Evaluadores consume `/admin/users` y filtra por `rol_id`. NO hacer un endpoint separado `/admin/evaluadores`; la fuente de verdad para roles es la tabla `users`.

### 14. Convenio PDF Dinámico
- **Relaciones necesarias:** Para el PDF del Convenio se requiere que la solicitud cargue las relaciones `user`, `user.cargo`, `institucion`, `institucion.acronimo`, `areaConocimiento`, y `convocatoria`.
- **Llamada correcta en el Controller:** `$solicitud->load(['user', 'institucion', 'convocatoria', 'areaConocimiento']);`
- **Campo `cargo`:** Actualmente en `users.cargo` (columna en la tabla de usuarios). Si no existe, usar `?? 'Representante Legal'` como fallback.

### 15. Configuración SMTP para Producción
Para que los correos salgan al mundo exterior, configura en `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.office365.com      # O smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=notificaciones@comecyt.gob.mx
MAIL_PASSWORD=tu_password_real
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=notificaciones@comecyt.gob.mx
MAIL_FROM_NAME="Sistema COMECYT"
```

### 16. Admin Layout - Reglas de Sidebar y Notificaciones
- **Sidebar completo:** El `navItems` del `admin/layout.tsx` debe incluir TODOS los módulos admin: Dashboard, Convocatorias, Solicitudes, Ministraciones, Instituciones, Revisores/Evaluadores, Gestión de Usuarios, Lista Negra.
- **Campana de notificaciones:** El `<Bell>` button debe tener un `onClick` que muestre un dropdown con datos reales de `/admin/notificaciones`. Nunca dejar el botón sin función.
- **Convocatoria Activa en Dashboard:** Se obtiene haciendo `api.get('/admin/convocatorias')` y filtrando por `estado === 'activa'`. Nunca hardcodear el nombre.
- **Botón Configurar:** Debe ser un `<a href>` o `<Link>` apuntando a la página de convocatorias, NO un botón sin `onClick`.
- **Avatar de usuario:** El nombre debe venir del cookie `userName`, no de texto fijo como 'Admin General'.

### 17. Patrón Universal de Nombre de Usuario en Layouts
- **Todos los layouts** (admin, revisor, evaluador, solicitante) deben usar `useState('Rol por defecto')` + `useEffect(() => { const n = Cookies.get('userName'); if(n) setUserName(n); })` para mostrar el nombre real del usuario.
- **Nunca hardcodear** strings como 'Equipo de Revisión', 'RV', 'EV', 'AD', 'Evaluador Externo', etc. en los layouts. Siempre usar `userName.charAt(0).toUpperCase()` para el avatar.
- **Botón de reportes / ver historial:** Siempre deben ser `<Link href="/ruta-real">` o `<a href>`, nunca `<button>` sin `onClick`.

> Este archivo es la memoria del proyecto. Léelo al inicio de cada sesión antes de hacer cambios.
> Actualízalo cuando se tomen decisiones de arquitectura importantes.

### 18. SolicitudController — Validación de Lista Negra
- Al crear una solicitud (`store`), siempre verificar `ListaNegra::where('institucion_id', $user->institucion_id)->where('activa', true)->exists()`.
- Si está en la lista, retornar 403 con `error: 'institucion_bloqueada'`.
- El frontend debe capturar ese error y mostrar un mensaje claro al solicitante.

### 19. EvaluadorController — Asignación Admin
- El endpoint `POST /admin/asignaciones-evaluador` verifica que el usuario tenga `rol_id === 3` antes de asignar.
- Al asignar, crea un `NotificacionLog` al evaluador y cambia el estado de la solicitud a `en_evaluacion`.
- Al emitir dictamen (`saveDictamen`), cambia el estado de la solicitud a `aprobada` o `rechazada` según el resultado.

### 20. Migraciones Seguras con Schema::hasColumn
- Siempre usar `if (!Schema::hasColumn('tabla', 'columna'))` antes de `$table->string(...)` en migraciones que puedan ejecutarse en bases de datos que ya tienen la columna.
- Esto evita errores "column already exists" en ambientes de staging/produccion donde las migraciones se aplican incrementalmente.

### 21. Errores Comunes con Migraciones y Seeders en Laravel 11 (30 Marzo 2026)

#### 21.1 Error: `Method dropForeignKeyIfExists does not exist`
- **Problema:** Al escribir `$table->dropForeignKeyIfExists(['columna'])` en el método `down()` de una migración, Laravel falla.
- **Causa:** Laravel 11 no tiene el método `dropForeignKeyIfExists()` en Blueprint. Este método no existe en la API de Schema.
- **Solución:** Eliminar la llamada a `dropForeignKeyIfExists()`. Generalmente basta con `$table->dropColumn('columna')` que automáticamente remueve la FK asociada. Si necesitas ser explícito, usa `$table->dropForeignKey(['columna'])` (sin el "IfExists"), pero verifica primero con `if (Schema::hasColumn())`.
- **Código correcto:**
  ```php
  public function down(): void {
      Schema::table('tabla', function (Blueprint $table) {
          $table->dropColumn(['columna1', 'columna2']);
      });
  }
  ```

#### 21.2 Error: `Method dropForeignKey does not exist`
- **Problema:** Incluso sin el "IfExists", `dropForeignKey()` falla en algunas versiones.
- **Causa:** La firma correcta es `$table->dropForeignKey(['columna'])` con array, pero PostgreSQL genera nombres de FK complejos. A veces el nombre generado no coincide.
- **Solución Recomendada:** En lugar de combatir con dropForeignKey, simplemente usa `$table->dropColumn()` que maneja automáticamente las constraints. Si necesitas rollback fino, es mejor NO hacer rollback de datos transaccionales en seeders; usa `migrate:fresh` en dev.

#### 21.3 Error: `VALUES lists must all be the same length` (PostgreSQL)
- **Problema:** Al usar `DB::table()->insertOrIgnore()` con múltiples registros donde algunos tienen columnas NULL y otros no.
  ```php
  DB::table('tipos_programa')->insertOrIgnore([
      ['id' => 1, 'nombre' => 'A', 'monto' => null],  // 3 columnas
      ['id' => 2, 'nombre' => 'B', 'monto' => 500],   // 3 columnas
      ['id' => 3, 'nombre' => 'C'],                   // 2 columnas ← ERROR
  ]);
  ```
- **Causa:** PostgreSQL (a diferencia de MySQL) requiere que TODAS las filas en un INSERT múltiple tengan exactamente el mismo número de columnas. Si una fila tiene menos campos, PostgreSQL rechaza el statement.
- **Solución:** Insertar registros de uno en uno, o asegurar que todas las filas tengan el mismo conjunto de claves:
  ```php
  foreach ($tipos as $tipo) {
      $tipo['activo'] = true;
      $tipo['created_at'] = now();
      $tipo['updated_at'] = now();
      DB::table('tipos_programa')->insertOrIgnore($tipo);
  }
  ```

#### 21.4 Error: `Relation "tabla" does not exist` en seeders tras `migrate:fresh --seed`
- **Problema:** Cuando corres `migrate:fresh --seed` en una sola línea, los seeders no ven las tablas recién creadas por las migraciones, incluso aunque el output muestre "DONE".
- **Causa:** Los seeders que usan Eloquent ORM (ej. `TipoPrograma::firstOrCreate()`) cargan el modelo antes de que la tabla exista en caché. El problema es de timing o de caché de reflexión en PHP.
- **Solución (Recomendada):** Usar `DB::table()` directamente en seeders en lugar de Eloquent ORM. Esto bypassa cualquier caché:
  ```php
  // ❌ MALO en fresh migrations:
  TipoPrograma::firstOrCreate(['clave' => 'PFPI'], [...]);

  // ✅ BUENO:
  DB::table('tipos_programa')->insertOrIgnore([...]);
  ```
- **Alternativa:** Si prefieres Eloquent, asegúrate de que la migración haya creado la tabla (verifica `php artisan migrate:status`) y luego corre los seeders por separado:
  ```bash
  php artisan migrate:fresh
  php artisan db:seed
  ```

---

## Visión General

**COMECYT** (Consejo Mexiquense de Ciencia y Tecnología) — Sistema de gestión de solicitudes de apoyo económico a eventos científicos y proyectos de vinculación/incorporación/prototipo.

El sistema administra el ciclo completo: convocatoria → solicitud → revisión → evaluación → convenio → ministración → informe final.

---

## Estructura del Monorepo

```
comecyt-system/
├── apps/
│   ├── web/          → Next.js 14 (App Router + React 18) — Frontend
│   └── api/          → Laravel 11 (API-only) — Backend
├── packages/
│   └── shared-types/ → TypeScript types compartidos (TS)
├── _agents/
│   ├── skills/       → Skills reutilizables en .md
│   └── workflows/    → Flujos automatizados de agente
├── CLAUDE.md         → Este archivo
├── .env.example      → Template de variables de entorno
└── .gitignore        → Archivos ignorados por git
```

---

## Stack Tecnológico

| Capa | Tecnología | Versión |
|---|---|---|
| Frontend | Next.js (App Router) + React | 14 / 18 |
| Estilos | Tailwind CSS + shadcn/ui | latest |
| Backend | Laravel (API-only) | 11 |
| Auth | Laravel Sanctum + JWT | - |
| Base de datos | PostgreSQL | 18 (EDB) |
| ORM | Eloquent | (Laravel 11) |
| Email (dev) | Mailpit | latest |
| Rate Limiting | Laravel middleware custom | - |
| Circuit Breaker | Cache-based (file store en dev) | - |

---

## Variables de Entorno

Las credenciales reales se guardan en `.env` (nunca committear). Ver `.env.example` para el template.

### Backend (`apps/api/.env`)
```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=comecyt_dev
DB_USERNAME=comecyt
DB_PASSWORD=12345678
```

### Frontend (`apps/web/.env.local`)
```
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

---

## Comandos Clave

### Setup inicial
```bash
# Backend
cd apps/api && composer install && cp .env.example .env && php artisan key:generate
php artisan migrate && php artisan db:seed

# Frontend
cd apps/web && npm install && cp .env.local.example .env.local
npm run dev
```

### Desarrollo diario
```bash
# Levantar todo (en terminales separadas)
cd apps/api && php artisan serve         # http://localhost:8000
cd apps/web && npm run dev               # http://localhost:3000
mailpit                                  # http://localhost:8025 (emails)
```

### Base de datos
```bash
cd apps/api
php artisan migrate                      # Migrar
php artisan migrate:fresh --seed         # Reset completo + seed
php artisan make:migration create_X_table --create=X
php artisan make:model X -msr            # Model + migration + seeder + resource
```

### Git
```bash
git add -A && git commit -m "feat: descripcion"  # Commit
git log --oneline -10                             # Últimos 10 commits
git checkout <hash>                               # Rescatar versión anterior
```

### Tests
```bash
cd apps/api && php artisan test           # Todos los tests
php artisan test --filter=NombreTest      # Test específico
```

---

## Roles del Sistema

| ID | Nombre | Descripción |
|---|---|---|
| 1 | Administrador | Control total: catálogos, convocatorias, asignaciones, ministraciones |
| 2 | Revisor | Revisión documental primaria de solicitudes |
| 3 | Evaluador | Evaluación técnica/académica de proyectos (criterios 25%×4) |
| 4 | Solicitante | Institución — crea solicitudes, sube docs, entrega informes |

---

## Paleta de Colores (MARCA.png)

```css
--color-primary:       #6B1F3A;  /* Vino oscuro — encabezados, botones */
--color-primary-hover: #8A2049;  /* Vino claro — hover */
--color-accent:        #C9A96E;  /* Dorado/Beige — acentos, badges */
--color-bg:            #F5F0F5;  /* Fondo de página */
--color-surface:       #FFFFFF;  /* Tarjetas y paneles */
--color-text:          #1E1E2E;  /* Texto principal */
--color-muted:         #6B7280;  /* Texto secundario */
```

---

## Flujo de Estados de una Solicitud

```
BORRADOR → ENVIADA → EN_REVISION → OBSERVADA ↺
                                  ↓
                            EN_EVALUACION → RECHAZADA
                                  ↓
                              APROBADA → CONVENIO → MINISTRACION → SEGUIMIENTO → CERRADA
                                                                              ↓
                                                                        CANCELADA
```

### Colores de Estado de Evaluación (Semáforo)
- **Naranja** → Evaluador notificado (pendiente)
- **Amarillo** → En proceso de evaluación
- **Verde** → Evaluación emitida

---

## Seguridad

### Rate Limiting
- `RateLimitMiddleware` → Máx. **100 req/min por IP**
- Respuesta: `HTTP 429 Too Many Requests`
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`

### API Gateway
- `ApiGatewayMiddleware` → Si hay ≥ **1000 IPs distintas** en ventana de 60s → modo lockdown 5 min
- Respuesta: `HTTP 503 Service Unavailable`

### Circuit Breaker
- `CircuitBreakerMiddleware` → Tras **5 errores 5xx consecutivos** → circuito abierto 30s
- Estados: `CLOSED → OPEN → HALF-OPEN → CLOSED`

---

## Convenciones de Código

### PHP / Laravel
- PSR-12, snake_case para BD, PascalCase para clases
- Un Controller por recurso/dominio
- Lógica de negocio en `Services/`, no en Controllers
- Validación en `FormRequest`, no inline
- Políticas de autorización en `Policies/`

### TypeScript / React
- Functional components + Hooks
- Tipos explícitos, no `any`
- Componentes en `PascalCase`, hooks en `camelCase`
- Barrel exports en `index.ts` por carpeta
- `lib/api.ts` es el único punto de comunicación con el backend

---

## Decisiones Importantes de Arquitectura

1. **Laravel Sanctum + SPA** — Frontend Next.js consume la API de Laravel via token Bearer. El token se almacena en `httpOnly cookies` en producción, `localStorage` en desarrollo.
2. **Screaming Architecture** — Carpetas nombradas por dominio de negocio, no por tipo técnico.
3. **PostgreSQL EDB 18** — Puerto 5432, instalación EnterpriseDB. Usuario `comecyt`, DB `comecyt_dev`. Contraseña en `.env` nunca hardcodeada.
4. **Un solo login** → redirección por rol tras autenticación.
5. **Mailpit** en desarrollo para emails.

---

## Contexto de Negocio Importante

- Las solicitudes tienen **tope de $60,000 MXN** y aportación mínima del 10% del solicitante.
- Evaluadores **no pueden evaluar proyectos de su propia institución** (restricción en catálogo).
- Proyectos con calificación **≥ 80/100** son sujetos de apoyo.
- El solicitante tiene **20 días hábiles** posteriores al evento para entregar informe final.
- El incumplimiento de acuerdos lleva a **lista negra por institución**.
- Toda la comunicación formal es **por correo a través del sistema**.

---

## Arquitectura: Programas Dinámicos (2026-03-30)

### 22. Programas COMECYT 100% DB-Driven ✅ COMPLETADO

**OBJETIVO:** Agregar un programa nuevo = INSERT en BD, CERO cambios de código.

**FASE 1: BASE DE DATOS ✅**
- 13 migraciones creadas (tipos_programa, programa_*, solicitud_*) — todas ejecutadas exitosamente
- 480 registros insertados (5 programas, 11 etapas, 4 modalidades, 25 criterios, 22 rubros)
- PostgreSQL: FK constraints, unique keys, indexes optimizados
- Pattern `DB::table()->insertOrIgnore()` usado en seeders (evita caché de ORM en fresh migrations)

**FASE 2: MODELOS LARAVEL ✅**
- 11 modelos creados: TipoPrograma + 6 catálogo + 4 transaccionales
- Relaciones completas: hasMany, belongsTo, eager loading
- Casts tipados: decimal(10,2), boolean, date, array
- Accessors/mutators para campos dinámicos

**FASE 3: API CONTROLLERS ✅**
- `ProgramaCatalogController` con 8 métodos (1.2KB, 150 líneas)
- 7 endpoints GET para catálogos dinámicos + 1 admin (clearCache)
- Cache inteligente: 5min TTL, granular por programa
- Eager loading: Zero N+1 queries
- Response wrapper: `{message, data, count?, timestamp}`
- Routes registradas en `api.php` (líneas 95-106)

**Endpoints Activos:**
```
GET /api/catalogs/programa/{id}              # Config completa (modalidades, etapas, campos, etc)
GET /api/catalogs/programa/{id}/campos       # Campos dinámicos del formulario
GET /api/catalogs/programa/{id}/documentos   # Documentos requeridos por etapa
GET /api/catalogs/programa/{id}/criterios    # Criterios evaluación (agrupados por etapa si aplica)
GET /api/catalogs/programa/{id}/rubros       # Rubros presupuestales autorizados
GET /api/catalogs/programa/{id}/etapas       # Fases/etapas del programa
GET /api/catalogs/programa/{id}/modalidades  # Modalidades del programa
POST /api/catalogs/programa/{id}/clear-cache # Admin: invalidar cache
```

**Programas Configurados:**
| Clave | Nombre | Tipo Apoyo | Etapas | Criterios | Rubros |
|-------|--------|-----------|--------|-----------|--------|
| PFPI | Reembolsos | reembolso | 1 | 0 | 0 |
| PROT | Prototipos | concurrente | 2 | 4 | 5 |
| IPFE | Prof. Extranjeros | honorarios | 2 | 0 | 4 |
| VINC | Vinculación | concurrente | 2 | 21 | 8 |
| EMP | Emprendedores | concurrente | 2 | 0 | 5 |

**Próximas Fases (NO INICIADAS):**
- Frontend: Componentes React para consumir catálogos dinámicos
- SolicitudController: Validación dinámica según `programa_campos`
- Admin UI: CRUD para gestionar programas sin restarts

**Restricción Cumplida:**
✅ Toda validación, campos, criterios, rubros y etapas vienen 100% de BD. El sistema interpreta programas sin hardcode.
✅ Agregar programa nuevo = 7 INSERTs en BD + sin cambios de código

---

## Registro de Cambios Recientes (2026-03-25)

### 21. Solicitudes: Error 422 y Guardar Borrador
- **Problema:** Al intentar guardar una nueva solicitud arrojaba error 422 (Unprocessable Entity).
- **Causa:** El frontend enviaba los campos `descripcion` y `monto_solicitado`, pero la API esperaba `resumen` y no validaba/guardaba el monto. Adicionalmente, el controlador `Solicitudes/SolicitudController` no tenía implementada la validación de Lista Negra.
- **Solución:** Se homologaron los campos en el controlador para aceptar `descripcion` (mapeado a `descripcion_proyecto`) y `monto_solicitado`. Se añadió un `accessor` en el modelo `Solicitud` para que el campo `resumen` sea accesible por el frontend (vía `$appends`). Se portó la lógica de validación de Lista Negra al controlador activo.

### 22. Controladores Duplicados
- **Nota:** Se identificó que existían dos `SolicitudController`. El activo y usado por las rutas es `App\Http\Controllers\Solicitudes\SolicitudController`. El que se encuentra en la raíz de `Controllers` es redundante y se deben aplicar los cambios solo al que está en el namespace `Solicitudes`.

### 23. Recuperación de Servidores
- **Acción:** Se reiniciaron los servicios de Backend (Artisan @ 8000) y Frontend (Next.js @ 3000) tras reporte de cierre del servidor.

---
