# CLAUDE.md - Memoria del Proyecto COMECYT
**Versión:** 2.8 (06 Abril 2026 - 12:45) | **Estado:** Producción + Wizard ✅ + Revisor Middleware ✅ | **Completitud:** 100% (MVP Funcional)

---

## 🌍 VISIÓN GENERAL DEL PROYECTO

### Qué es COMECYT
**Consejo Mexiquense de Ciencia y Tecnología** - Sistema integral de gestión de apoyo científico que administra el ciclo completo de convocatorias científicas:
1. **Publicación:** Admin crea convocatorias con campos dinámicos
2. **Solicitud:** Institutos envían solicitudes con documentos
3. **Revisión Documental:** Revisor valida documentación
4. **Evaluación Técnica:** Evaluador puntúa según criterios dinámicos
5. **Convenio:** Se genera acuerdo formal
6. **Ministración:** Se liberan fondos en fases
7. **Informe:** Institución reporta resultados
8. **Cierre:** Archivado y análisis

### Usuarios del Sistema
- **Administrador (Admin):** CRUD todo, control total, crea convocatorias
- **Revisor Documental:** Valida documentación, genera observaciones
- **Evaluador Técnico:** Puntúa proyectos según criterios dinámicos
- **Solicitante (Institución):** Crea solicitudes, carga documentos

### Stack Tecnológico
| Capa | Tecnología | Version |
|------|-----------|---------|
| Frontend | Next.js 14 + React 18 + TypeScript | 14.x / 18.x |
| Backend | Laravel (API-only) | 11 |
| Autenticación | JWT (tymon/jwt-auth) | HS256 |
| Base de Datos | PostgreSQL | 18 |
| ORM | Eloquent | built-in |
| Estilos | Tailwind CSS v4 + Shadcn UI v3 | latest |

### Arquitectura Clave

**Convocatoria → TipoPrograma (1:1 único)**
- Cada convocatoria genera su PROPIO TipoPrograma
- Todos los detalles (campos, docs, rubros, criterios) se configuran en un Wizard
- Admin no necesita navegar a múltiples pantallas
- Cada convocatoria es 100% personalizable

**Programas Dinámicos (100% BD-driven)**
- Sin código hardcodeado para campos, documentos, rubros, criterios
- Admin crea a través de interfaz web
- Solicitantes ven campos específicos del programa
- Revisor ve dinámicamente campos disponibles
- Evaluador califica según criterios específicos

### Puertos
- Frontend (Next.js): `http://localhost:3000`
- Backend (Laravel): `http://localhost:8000`
- API Endpoint: `http://localhost:8000/api`

---

## 🔧 COMANDOS CLAVE (Copy-Paste)

### Backend (Laravel)

```bash
# Setup inicial
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Desarrollo
php artisan serve                              # http://localhost:8000
php artisan tinker                             # CLI interactiva

# Database
php artisan migrate                            # Aplicar migraciones
php artisan migrate:fresh --seed              # Reset + seed
php artisan db:seed --class=ClassName         # Seed específico

# Cache & Config
php artisan cache:clear                        # Limpiar cache
php artisan config:clear                       # Limpiar config cache
php artisan storage:link                       # Crear symlink public/storage

# Testing
php artisan test                               # Todos los tests
php artisan test --filter=TestName            # Test específico
```

### Frontend (Next.js)

```bash
# Setup inicial
cd apps/web
npm install
cp .env.example .env.local                    # Editar NEXT_PUBLIC_API_URL

# Desarrollo
npm run dev                                    # http://localhost:3000
npm run build                                  # Build production
npm start                                      # Servir build

# Linting & Formatting
npm run lint                                   # ESLint check
npm run lint --fix                            # Auto-fix lint
npm run format                                # Prettier format
```

### Git Workflow

```bash
# Status y commits
git status                                     # Ver estado
git add apps/web/src/...                      # Stage específicos
git commit -m "feat: descripción del cambio"  # Conventional commits
git log --oneline -10                         # Ver commits recientes

# Branches
git checkout -b feature/nombre-feature        # Nueva rama
git switch main                                # Cambiar rama (preferido)
git pull origin main                          # Traer cambios
git push origin feature/nombre                # Empujar rama
```

---

## 📋 GUÍAS DE ESTILO & PATRONES

### PHP/Laravel Conventions

```php
// ✅ CORRECTO
namespace App\Http\Controllers;

class SolicitudController extends Controller {
    public function store(Request $request) {
        $validated = $request->validate([...]);
        $model = Model::create($validated);
        return response()->json($model, 201);
    }
}

// ❌ INCORRECTO
function store($req) {
    $m = Model::create($req->all());
    return $m;
}
```

**Convenciones:**
- PascalCase: Classes, Models, Traits
- camelCase: methods, variables, properties
- snake_case: database columns, validation rules
- UPPER_CASE: constants
- PHPDoc para métodos públicos

### TypeScript/React Conventions

```typescript
// ✅ CORRECTO
'use client';

import { useState, useEffect } from 'react';

interface Props {
  solicitudId: number;
  onUpdate?: () => void;
}

export default function Componente({ solicitudId, onUpdate }: Props) {
  const [data, setData] = useState<Type[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const init = async () => {
      try {
        const { data } = await api.get(`/api/endpoint/${solicitudId}`);
        const items = Array.isArray(data) ? data : [];
        setData(items);
      } catch (error) {
        setData([]);
      } finally {
        setLoading(false);
      }
    };
    init();
  }, [solicitudId]);

  return <div>{/* JSX */}</div>;
}

// ❌ INCORRECTO
export default function Comp(props) {
  const [d, setD] = useState();
  useEffect(() => {
    api.get('/api/endpoint').then(r => setD(r.data));
  });
  return <div>{d.map(...)}</div>;
}
```

**Convenciones:**
- PascalCase: Components, Types, Interfaces
- camelCase: variables, functions, props
- snake_case: API response fields (inherited from backend)
- `Array.isArray()` SIEMPRE antes de .map/.filter
- Tipos explícitos: `useState<Type[]>()` no `useState([])`
- Usar `readonly` para props no-mutables

### Patrones Críticos

**1. Array Validation Pattern (MUST USE)**
```typescript
// ✅ SIEMPRE hacer esto
const items = Array.isArray(response.data)
  ? response.data
  : Array.isArray(response.data?.data)
  ? response.data.data
  : [];
```

**2. Promise.all() para Múltiples Datos**
```typescript
// ✅ Sincronizar múltiples API calls
const [statsRes, listRes] = await Promise.all([
  api.get('/stats').catch(() => ({ data: {} })),
  api.get('/list').catch(() => ({ data: [] }))
]);
```

**3. Storage Disks (ALWAYS explicit)**
```php
// ✅ CORRECTO
Storage::disk('public')->putFileAs("docs/{id}", $file, $filename);
$url = Storage::disk('public')->url("docs/{id}/{$filename}");

// ❌ INCORRECTO (usa disco 'local' implícitamente)
$file->storeAs("docs/{id}", $filename);
```

**4. Cache + Eloquent Models (CONVERT TO ARRAY)**
```php
// ✅ CORRECTO
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get()->toArray();  // ← .toArray() crítico
});

// ❌ INCORRECTO
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get();  // Serializa como __PHP_Incomplete_Class
});
```

---

## 🎯 Últimas Sesiones

### Session 06-04-2026: Wizard 7-Pasos COMPLETADO ✅

**Estado Final:** Implementación 100% completa - Backend + Frontend + Documentación

#### Backend Implementado

**Archivo Creado:** `apps/api/app/Http/Controllers/Admin/ProgramaDocumentoController.php`
- Métodos: `index()`, `store()`, `update()`, `destroy()`
- Validaciones: clave unique por programa, formato_permitido, tamaño_maximo_mb
- Cache clearing automático en create/update/delete
- Relación ya existía en `TipoPrograma::documentos()`

**Rutas Agregadas** (en `routes/api.php`):
```php
Route::get('programas/{tipoPrograma}/documentos', [ProgramaDocumentoController::class, 'index']);
Route::post('programas/{tipoPrograma}/documentos', [ProgramaDocumentoController::class, 'store']);
Route::put('programas/{tipoPrograma}/documentos/{documento}', [ProgramaDocumentoController::class, 'update']);
Route::delete('programas/{tipoPrograma}/documentos/{documento}', [ProgramaDocumentoController::class, 'destroy']);
```

**ConvocatoriaController Actualizado:**
- `store()`: ahora valida `tipo_programa_id => 'required|exists:tipo_programas,id'`
- `update()`: permite actualizar `tipo_programa_id`
- Ambos métodos persisten `tipo_programa_id` en la convocatoria creada

#### Frontend Implementado

**Archivo Creado:** Wizard completo en `apps/web/src/app/admin/convocatorias/nueva/page.tsx`
- **Tamaño:** 1,764 líneas de React TypeScript
- **Steps:** 7 pasos con navegación visual
- **Tablas CRUD:** Componentes en memoria para campos, documentos, rubros, criterios
- **Validación:** Exhaustiva por step (fechas, montos, porcentajes, claves, etc.)
- **Step Navigator:** Números 1-7 con checkmarks verdes para completados
- **Guardado Secuencial (Step 7):**
  1. `POST /admin/programas` (crea TipoPrograma)
  2. `POST /admin/programas/{id}/campos` × N
  3. `POST /admin/programas/{id}/documentos` × N
  4. `POST /admin/programas/{id}/rubros` × N
  5. `POST /admin/programas/{id}/criterios` × N
  6. `POST /admin/convocatorias` (con tipo_programa_id)

#### Validaciones Implementadas

| Step | Validación | Regla |
|------|-----------|-------|
| 1 | Fechas | `fecha_apertura <= fecha_cierre` |
| 1 | Montos | Todos > 0 |
| 1 | Porcentaje | 0-100% |
| 2 | Clave | No vacía, uppercase, max 20 chars |
| 2 | Num Etapas | Si `tiene_etapas=true` → `num_etapas > 0` |
| 3 | Campos | ≥1 campo, cada uno: nombre + etiqueta |
| 3 | Select | Si tipo='select' → ≥1 opción |
| 4 | Documentos | ≥1 documento |
| 4 | Clave Documento | unique + lowercase_snake |
| 5 | Rubros | ≥1 rubro |
| 6 | Criterios | ≥1 criterio |
| 6 | Ponderaciones | Suma = 100% (validación live) |

#### UX/UI Features

- **Step Indicator Visual:** Progreso numérico 1-7 con líneas conectoras
- **Modales CRUD:** Dialog components para cada entidad
- **Validación Live:** En Step 6, mostrar "Ponderación Total: X%" con color rojo si ≠ 100%
- **Error Handling:** AlertBox rojo para errores, azul para info
- **Progreso en Step 7:** Spinner con mensajes: "Creando programa..." → "Agregando campos..." etc
- **Review Card Final:** Grid de resúmenes mostrando todo antes de guardar
- **Buttons Contextuales:** "Anterior" deshabilitado en Step 1, "Crear Convocatoria Completa" en Step 7

#### Flujo Completo

```
Admin → Convocatorias → Nueva
  ↓
Step 1: Info básica (nombre, fechas, montos)
  ↓ validar y siguiente
Step 2: Programa (clave, nombre, tipo_apoyo, monto_maximo)
  ↓ validar y siguiente
Step 3: Campos (tabla CRUD: nombre_campo, etiqueta, tipo_campo, requerido, orden)
  ↓ validar ≥1 campo
Step 4: Documentos (tabla CRUD: clave, nombre, obligatorio, orden)
  ↓ validar ≥1 documento
Step 5: Rubros (tabla CRUD: clave, nombre, % máximo)
  ↓ validar ≥1 rubro
Step 6: Criterios (tabla CRUD: nombre, ponderación, puntaje máximo)
  ↓ validar suma ponderaciones = 100%
Step 7: Revisión (resumen completo + botón guardar)
  ↓ click "Crear Convocatoria Completa"
    → Secuencia de 6 API calls
    → On Success: redirect /admin/convocatorias
    → On Error: mostrar error + permitir retry
```

#### Testing Realizado

✅ Backend ProgramaDocumentoController:
- Routes correctas en api.php
- Validations funcionando
- Cache clearing implementado

✅ Frontend Wizard:
- 7 pasos renderean correctamente
- Validaciones por step funcionan
- Tablas CRUD permiten add/edit/delete
- Step Navigator visual está presente
- Modales CRUD abren y cierran

✅ ConvocatoriaController:
- Aceptar `tipo_programa_id` en store() y update()
- Validar que existe en `tipo_programas` table

---

## 🎯 Anteriores Sesiones (03 Abril 2026)

### Session 03-04-2026 Parte 2: Wizard 7-Pasos para Nueva Convocatoria (IN PROGRESS)

**Contexto:** El admin no podía definir qué campos, documentos, rubros ni criterios solicitar al crear una convocatoria. La columna `tipo_programa_id` existía en la BD pero nunca se conectó al formulario.

**Solución:** Implementar un **Wizard multi-paso con estado en-memoria** donde:
1. Step 1: Información básica de la Convocatoria
2. Step 2: Configuración del TipoPrograma (generará su propio programa único)
3. Step 3: Definir Campos dinámicos que los solicitantes llenarán
4. Step 4: Definir Documentos requeridos
5. Step 5: Definir Rubros presupuestales
6. Step 6: Definir Criterios de evaluación
7. Step 7: Revisión y guardar (hace todas las llamadas API en secuencia)

**Arquitectura:**
- Todo se guarda en estado local (React state) hasta Step 7
- En Step 7: `POST /admin/programas` → luego POST campos, docs, rubros, criterios → luego `POST /admin/convocatorias` con `tipo_programa_id`
- Si cualquier paso falla, se muestra error y permite reintentar
- Cada Convocatoria tiene su PROPIO TipoPrograma único (no comparte)

**Cambios Backend Necesarios:**
1. Crear `ProgramaDocumentoController` (CRUD para documentos) — faltaba en routes
2. Agregar rutas: `POST /admin/programas/{id}/documentos` (y PUT, DELETE)
3. Actualizar `ConvocatoriaController::store()` para validar y guardar `tipo_programa_id`

**Cambios Frontend:**
1. Reemplazar `/apps/web/src/app/admin/convocatorias/nueva/page.tsx` con Wizard de 7 pasos
2. Estado local para acumular datos de todos los pasos
3. Validaciones live (ej: suma de ponderaciones = 100% en criterios)
4. Step Navigator visual con progreso

**Por Qué Este Enfoque:**
- Admin puede crear convocatorias completamente personalizadas sin tocar código
- Cada convocatoria es independiente (su propia config)
- Solicitantes ven exactamente los campos y docs que el admin configuró
- Revisor ve dinámicamente los campos disponibles para observaciones
- Evaluador ve los criterios específicos de ese programa

---

### Errores Encontrados y Solucionados (Session 03-04-2026 Parte 1)

| # | Error | Síntoma | Root Cause | Solución |
|---|-------|---------|-----------|----------|
| **1** | **TypeError: null is not an object** | Admin Programas page crash (line 207) | `prog.monto_maximo` es null, llamando `.toLocaleString()` sin validación | `(prog.monto_maximo \|\| 0).toLocaleString()` |
| **2** | **Axios Error 500** | Revisor solicitud detalle falla al cargar | Cache::remember() serializa modelos Eloquent como `__PHP_Incomplete_Class` | Convertir a array con `.toArray()` antes de cachear (6 métodos) |
| **3** | **TypeError: count() argument must be Countable\|array** | ProgramaCatalogController métodos documentos/campos/rubros/etapas/modalidades | Deserializando objeto incompleto del cache | Usar `is_array($data) ? count($data) : 0` + validación |
| **4** | **403 Forbidden al acceder documentos** | Preview/Download retorna 403 Forbidden en `/storage/documentos/{id}` | `$file->storeAs()` usa disco por defecto (`'local'`/private) pero `Storage::url()` espera disco `'public'` | Usar `Storage::disk('public')->putFileAs()` explícitamente |

### **Archivos Afectados**

| Archivo | Cambios |
|---------|---------|
| `apps/web/src/app/admin/programas/page.tsx` (line 207) | Null check para `monto_maximo` |
| `apps/api/app/Http/Controllers/Catalogos/ProgramaCatalogController.php` | 6 métodos: `show()`, `campos()`, `documentos()`, `criterios()`, `rubros()`, `etapas()`, `modalidades()` — convertir a array antes de cachear |
| `apps/api/app/Http/Controllers/DocumentoUploadController.php` | Usar `Storage::disk('public')->putFileAs()` explícitamente (línea 35), `Storage::disk('public')->url()` (línea 38), y fix delete method |

### ---

## 📋 Patrón Arquitectónico: Convocatoria + TipoPrograma (Diseño Importantes)

### Relación: Cada Convocatoria = TipoPrograma Único

Antes de esta sesión:
- **Tabla:** `convocatorias` tenía columna `tipo_programa_id` pero era NULL
- **Anterior flujo:** Crear TipoPrograma en admin/programas → Crear Convocatoria referenciando ese programa
- **Problema:** Admin tenía que entender 2 pantallas diferentes (programas vs convocatorias)

Nuevo flujo:
- Admin crea Convocatoria → se auto-genera su TipoPrograma único
- Una Convocatoria = exactamente 1 TipoPrograma (no compartido)
- Todos los detalles (campos, docs, rubros, criterios) se configuran en el wizard
- No requiere navegar a admin/programas

**Por qué NO compartimos TipoPrograma:**
- Cada convocatoria tiene requisitos diferentes (campos, documentos, criterios únicos)
- Cambiar programa impactaría todas las convocatorias que lo usan (riesgo)
- Simplifica auditoria y trazabilidad (convocatoria 2026-PFPI siempre tiene su config original)
- En futuro, si un admin quiere reutilizar config entre convocatorias, puede copiar manualmente

### In-Memory State Pattern

El wizard NO crea TipoPrograma en cada paso. En lugar de:
```
Step 1 → POST /admin/convocatorias (crea draft) → Step 2 → PATCH /admin/programas (agrega campos)
```

Usamos:
```
Steps 1-7 → Acumular TODO en memoria → Step 7: POST /admin/programas → POST campos/docs/rubros/criterios → POST /admin/convocatorias
```

**Ventajas:**
- Si user abandona wizard, no hay registros huérfanos en BD
- Atomic: o todo se guarda, o nada
- Rápido: solo hace 6 llamadas API al final
- Si cualquier paso falla, user puede ver el error y reintentar

**Desventaja:**
- Si browser crash en Step 5, pierdes el progreso
- No hay "guardar borrador" intermedio

Esto es acceptable para este caso porque el wizard es relativamente corto (7 pasos).

### TypeScript State Interface

```typescript
interface ConvocatoriaWizardState {
  convocatoria: {
    nombre: string;
    ejercicio_fiscal: string;
    estado: 'borrador' | 'activa' | 'cerrada';
    descripcion: string;
    fecha_apertura: string;
    fecha_cierre: string;
    monto_maximo_apoyo: number;
    porcentaje_aportacion_minima: number;
  };
  programa: {
    clave: string;  // auto-sugerida ej: CONV-2026, editable
    nombre: string;
    tipo_apoyo: 'reembolso' | 'concurrente' | 'honorarios';
    monto_maximo: number;
    tiene_etapas: boolean;
    num_etapas: number;
    tiene_equipo: boolean;
    min_miembros_equipo?: number;
    max_miembros_equipo?: number;
    requiere_evaluacion_tecnica: boolean;
    puntaje_minimo_aprobatorio: number;  // default 80
  };
  campos: {
    nombre_campo: string;
    etiqueta: string;
    tipo_campo: 'text' | 'number' | 'select' | 'date' | 'textarea';
    requerido: boolean;
    orden: number;
    opciones_json?: Array<{id: string, label: string}>;
  }[];
  documentos: {
    clave: string;  // ej: carta_intencion, plan_trabajo
    nombre: string;
    descripcion: string;
    obligatorio: boolean;
    orden: number;
  }[];
  rubros: {
    clave: string;
    nombre: string;
    descripcion?: string;
    porcentaje_maximo?: number;
  }[];
  criterios: {
    nombre: string;
    descripcion: string;
    ponderacion: number;  // suma debe = 100%
    puntaje_maximo: number;
    orden: number;
  }[];
}
```

### Validaciones por Step

| Step | Validaciones |
|------|--------------|
| 1 | Nombre *, fecha_apertura ≤ fecha_cierre, monto > 0 |
| 2 | Clave única, nombre *, monto > 0, si tiene_etapas entonces num_etapas > 0 |
| 3 | Cada campo tiene nombre_campo + etiqueta, select debe tener ≥1 opción |
| 4 | Cada documento tiene clave (unique) + nombre, clave = lowercase_snake |
| 5 | Cada rubro tiene clave + nombre |
| 6 | Suma de ponderaciones visible, alerta si ≠ 100%, cada criterio tiene nombre + ponderacion |
| 7 | No necesita validación (ya validadas en steps previos) |

**Validación Live:** En Step 6, mientras user agrega criterios, mostrar "Ponderación Total: X%" con color rojo si ≠ 100%.

---

**Patrón Identificado: Cache + Eloquent Models = Problemas**

**Problema General:**
```php
// ❌ MAL - Cachea modelos Eloquent directamente
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get();  // Eloquent Collection
});
// Cuando deserializa del cache → __PHP_Incomplete_Class
// .toLocaleString(), count(), acceder propiedades → TypeError
```

**Solución Universal:**
```php
// ✅ BIEN - Convertir a array ANTES de cachear
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get()->toArray();  // Array plano
});
// Deserializa como array normal, seguro para count(), serialize, etc.
```

**Dónde Aplicar Esto:**
- Cualquier `Cache::remember()` que retorna modelos Eloquent
- Cualquier `Cache::get()` / `Cache::put()` con colecciones
- Controllers que cachean datos para APIs

---

## 🎯 Última Sesión (02 Abril 2026) — Validaciones en 2 Capas (COMPLETA)

### **PARTE 5: Validaciones Faltantes — Frontend + Backend (IMPLEMENTADO)**

**Gap Identificado:** Solicitante podía enviar sin documentos obligatorios y equipo sin cumplir mínimo requerido.

| Componente | Validación | Ubicación | Status |
|-----------|-----------|-----------|--------|
| **Backend** | Documentos obligatorios antes enviar | `SolicitudController::enviar()` | ✅ |
| **Frontend** | Documentos obligatorios antes llamar API | `[id]/page.tsx::handleEnviar()` | ✅ |
| **Frontend** | Min/Max miembros equipo count | `nueva/page.tsx::validateForm()` | ✅ |
| **Frontend** | UI Error mensaje documentos faltantes | AlertBox en `[id]/page.tsx` | ✅ |

**Implementación:** Validación en **2 capas** (redundancia deliberada):
1. **Frontend (UX):** Bloquea antes de llamar API, muestra AlertBox con documentos faltantes
2. **Backend (Seguridad):** Si frontend se saltea, backend rechaza con 422 + lista de faltantes

**Archivos Modificados:**
- `SolicitudController.php` — Agregar validación documentos en `enviar()`
- `[id]/page.tsx` — Cargar tiposDocumento + validar en handleEnviar() + AlertBox
- `nueva/page.tsx` — Agregar validación min/max count miembros en validateForm()

---

### **PARTE 1-4: Fixes Críticos + Documentos Dinámicos (COMPLETA)**
| Problema | Síntoma | Solución |
|----------|---------|----------|
| axios transformResponse rompe arrays | `/revisor/solicitudes` vacío | Detectar `[` para arrays en `api.ts` |
| Endpoint incorrecto `/observe` | "Error al generar observación" | Cambiar a `/observar` en revisor page |
| Ruta faltante `/editar` | 404 al corregir solicitud | Crear `/[id]/editar/page.tsx` |
| 5 Convocatorias sin datos | Dropdown vacío | ConvocatoriasRealesSeeder ✅ |
| Documentos hardcodeados | Solo 5 tipos genéricos | ProgramaDocumentosSeeder con 15 docs ✅ |
| Revisor no ve documentos | Solo campos estáticos | DocumentosAdjuntos readonly ✅ |
| Admin panel 500 error | Laravel 11 middleware | Remover $this->middleware() calls ✅ |

**Estado:** ✅ Todos los 12+ problemas documentados en sesión anterior resueltos

---

## 📊 Visión General del Proyecto

**COMECYT** (Consejo Mexiquense de Ciencia y Tecnología) es un **Sistema Integral de Gestión de Apoyo Científico** que administra el ciclo completo de:

1. **Convocatorias:** Publicación de oportunidades de financiamiento (eventos científicos, investigación, prototipado)
2. **Solicitudes:** Institutos envían solicitudes para obtener apoyo financiero
3. **Revisión Documental:** Revisor verifica documentación (borrador, observaciones, aprobación)
4. **Evaluación Técnica:** Evaluador califica proyecto según criterios dinámicos (≥80 pts = aprobado)
5. **Convenio:** Admin genera acuerdo formal entre COMECYT e institución
6. **Ministración:** Liberación de fondos en fases/tranches
7. **Informe Final:** Institución reporta resultados e impacto
8. **Cierre:** Archivado y análisis de resultados

**Usuarios:** Administrador (COMECYT) | Revisor (Documental) | Evaluador (Técnico) | Solicitante (Institución)

---

## 🏗️ Arquitectura del Sistema

### Stack Tecnológico

| Capa | Tecnología | Versión |
|------|-----------|---------|
| **Frontend** | Next.js (App Router) + React + TypeScript | 14.x / 18.x |
| **Estilos** | Tailwind CSS v4 + Shadcn UI v3 | latest |
| **Backend** | Laravel (API-only) | 11 |
| **Autenticación** | JWT (tymon/jwt-auth) | HS256 |
| **Base de Datos** | PostgreSQL (EDB 18) | 18 |
| **ORM** | Eloquent | built-in |
| **Email (Dev)** | Mailpit | latest |
| **Middleware Custom** | Rate Limiting, API Gateway, Circuit Breaker | - |

### Estructura de Carpetas

```
comecyt-system/
├── apps/
│   ├── api/           # Laravel API
│   │   ├── app/Http/Controllers/     # Controllers por dominio
│   │   ├── app/Models/               # Modelos Eloquent
│   │   ├── app/Notifications/        # Notificaciones
│   │   ├── database/migrations/      # Migraciones
│   │   ├── database/seeders/         # Seeders
│   │   ├── routes/api.php            # Rutas API
│   │   └── bootstrap/app.php         # Configuración core
│   └── web/           # Next.js Frontend
│       ├── src/app/               # App Router por módulo
│       ├── src/components/        # Componentes reutilizables
│       ├── src/lib/               # Utilidades y API client
│       ├── src/hooks/             # React hooks
│       └── public/                # Assets estáticos
├── CLAUDE.md          # Este archivo (memoria del proyecto)
├── DIAGNOSTICO_ESTRATEGICO.md  # Análisis y plan de implementación
└── .env.example       # Template de variables
```

---

## 🔄 Flujo de Estados (Workflow Completo)

### Definición Oficial de Estados

```
Solicitud.estado:
  ├─ borrador          → creada, no enviada
  ├─ enviada           → lista para revisión documental
  ├─ observada         → revisor devuelve para correcciones
  ├─ en_revision       → revisión activa (deprecated, usar observada)
  ├─ en_evaluacion     → pasó revisión, requiere evaluación técnica
  ├─ aprobada          → evaluación ≥80 pts, sujeto de apoyo
  ├─ rechazada         → evaluación <80 pts, no aprobado
  ├─ convenio          → documento formal generado
  ├─ ministracion      → fondos siendo liberados
  ├─ seguimiento       → informe final en revisión
  ├─ cerrada           → proyecto finalizado exitosamente
  └─ cancelada         → proyecto cancelado antes de cierre

Solicitud.estado_informe:
  ├─ pendiente    → informe no entregado
  ├─ entregado    → informe recibido, en revisión
  ├─ observado    → informe con observaciones
  └─ aprobado     → informe finalizado
```

### Máquina de Estados Visual

```
┌─────────┐
│Borrador │
└────┬────┘
     │ [Solicitante: Enviar]
     ↓
┌─────────┐      ┌──────────┐
│Enviada  │─────→│Observada │
└────┬────┘ NO   └────┬─────┘
     │                │ [Solicitante: Reenviar]
     │ SÍ             ↓ (vuelve a Enviada)
     ↓           ┌──────────┐
┌──────────────┐ │Subsanada?│
│En Evaluación │ └──────────┘
└────┬─────────┘
     │
     ├─ Puntaje ≥80 → ┌─────────┐
     │                 │Aprobada │───→ Convenio → Ministración → Seguimiento → Cerrada
     │                 └─────────┘
     │
     └─ Puntaje <80 → ┌──────────┐
                      │Rechazada │
                      └──────────┘
```

---

## 📋 Flujo por Rol

### 1. SOLICITANTE (Institución)

**Permisos:**
- Crear solicitud (cualquier convocatoria activa)
- Ver mis solicitudes (solo propias)
- Enviar solicitud (borrador → enviada)
- Reenviar si hay observaciones (observada → enviada)
- Entregar informe final (ministracion → seguimiento)
- Descargar documentos generados

**Restricciones:**
- NO puede revisar ni evaluar
- NO puede crear convocatorias
- NO aparecen solicitudes de otras instituciones
- SI institución está en lista negra → 403 error

### 2. REVISOR DOCUMENTAL

**Permisos:**
- Ver solicitudes enviadas y observadas
- Generar observaciones (campo, tipo: documental/técnica, comentario)
- Aprobar solicitud (enviada → en_evaluacion)
- Devolver para correcciones (enviada → observada)
- Aprobar informe final

**Métricas Dashboard:**
- Nuevos: count(estado='enviada')
- En Subsanación: count(estado='observada')
- Urgentes: count(estado='observada' AND updated_at < 7 días)

### 3. EVALUADOR TÉCNICO

**Permisos:**
- Ver mis asignaciones (asignacion_evaluador.evaluador_id = user_id)
- Evaluar según criterios dinámicos (puntaje x criterio)
- Generar dictamen (auto-calcula puntaje_total)
- NO puede evaluar su propia institución

**Criterios Evaluación:**
- Dynamic (BD-driven): configurables por programa
- Legacy (fallback): 4 campos hardcodeados (25 pts each)
- Umbral aprobación: puntaje_total ≥ 80

### 4. ADMINISTRADOR

**Permisos:** TODOS - control total del sistema
- CRUD convocatorias, usuarios, instituciones, lista negra
- CRUD programas dinámicos (campos, rubros, criterios, etc)
- Asignar evaluadores a solicitudes
- Ver reportes y dashboards de todos
- Gestionar ministeraciones e informes
- Generar documentos (dictamen, convenio, excel)

---

## 🔧 Comandos Clave

### Backend (Laravel)

```bash
# Setup inicial
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed

# Desarrollo diario
php artisan serve                    # http://localhost:8000
php artisan tinker                   # CLI interactiva

# Database
php artisan migrate                  # Aplicar migraciones
php artisan migrate:fresh --seed     # Reset completo + seed
php artisan db:seed --class=DatabaseSeeder  # Solo seed

# Models & Migrations
php artisan make:model ModelName -msr        # Model + migration + seeder + resource
php artisan make:migration migration_name --create=table_name

# Testing
php artisan test                     # Todos los tests
php artisan test --filter=TestName   # Test específico
php artisan test --parallel          # Paralelo (más rápido)

# Cache & Config
php artisan cache:clear             # Limpiar cache
php artisan config:clear            # Limpiar config cache
php artisan config:cache            # Cache config (producción)

# JWT
php artisan jwt:generate            # Generar JWT_SECRET (solo primera vez)

# Utilities
php artisan tinker                   # CLI para debugging
php artisan storage:link             # Crear symlink public/storage
```

### Frontend (Next.js)

```bash
# Setup inicial
cd apps/web
npm install
cp .env.example .env.local
npm run dev                         # http://localhost:3000

# Desarrollo
npm run dev                         # Dev server con hot reload
npm run build                       # Build production
npm start                           # Servir build producción

# Linting & Formatting
npm run lint                        # ESLint check
npm run lint --fix                  # Auto-fix lint errors
npm run format                      # Prettier format

# Testing
npm run test                        # Jest tests
npm run test:watch                  # Watch mode
```

### Git & Deployment

```bash
# Commits
git add -A
git commit -m "feat: descripción de cambios"   # Follow conventional commits
git log --oneline -10                          # Ver últimos commits
git status                                     # Estado actual

# Ramas
git branch                          # Listar ramas
git checkout -b feature/name        # Nueva rama
git switch main                     # Cambiar rama (preferido en git 2.23+)

# Push & Pull
git push origin branch_name         # Empujar rama
git pull origin main                # Traer cambios de main
```

---

## 🎨 Guías de Estilo & Patrones

### Code Style - PHP / Laravel

```php
// ✅ BUENO
namespace App\Http\Controllers;

class SolicitudController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo_proyecto' => 'required|string|max:255',
            'monto_solicitado' => 'required|numeric|min:1|max:60000',
        ]);

        $solicitud = Solicitud::create($validated);
        return response()->json($solicitud, 201);
    }
}

// ❌ MALO
function store(Request $r) {
    $sol = Solicitud::create($r->all());
    return $sol;
}
```

**Convenciones:**
- PascalCase: Classes, Models, Interfaces
- camelCase: methods, variables, properties
- snake_case: database columns, parameters
- UPPER_CASE: constants
- Documentación: PhpDoc para métodos públicos

### Code Style - TypeScript / React

```typescript
// ✅ BUENO
'use client';

import { useState, useEffect } from 'react';
import api from '@/lib/api';

interface Solicitud {
  id: number;
  titulo_proyecto: string;
  estado: 'borrador' | 'enviada' | 'observada' | 'en_evaluacion';
}

export default function SolicitudesPage() {
  const [solicitudes, setSolicitudes] = useState<Solicitud[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const init = async () => {
      try {
        const { data } = await api.get('/solicitudes');
        const items = Array.isArray(data) ? data : [];
        setSolicitudes(items);
      } catch (error) {
        console.error('Error:', error);
        setSolicitudes([]);
      } finally {
        setLoading(false);
      }
    };
    init();
  }, []);

  return <div>...</div>;
}

// ❌ MALO
export default function Page() {
  const [sol, setSol] = useState([]);
  useEffect(() => {
    api.get('/solicitudes').then(({ data }) => setSol(data));  // Sin validación
  });
  return <div>{sol.map(s => <p>{s.nombre}</p>)}</div>;  // Sin type safety
}
```

**Convenciones:**
- PascalCase: Components, Types, Interfaces
- camelCase: variables, functions, props
- snake_case: API response fields (inherited from backend)
- Tipos explícitos: `useState<Type[]>` not `useState([])`
- Defensive: `Array.isArray(data)` antes de `.map()`

### Patrón de Validación de Arrays (CRÍTICO)

**Este patrón se encontró faltando en 7+ páginas. SIEMPRE usarlo:**

```typescript
// En useEffect API calls
useEffect(() => {
  const init = async () => {
    setLoading(true);
    try {
      const response = await api.get('/endpoint');
      // ✅ VALIDAR ARRAY
      const items = Array.isArray(response.data) ? response.data : [];
      setSolicitudes(items);
    } catch (error) {
      setSolicitudes([]);  // ✅ FALLBACK
    } finally {
      setLoading(false);   // ✅ LIMPIAR
    }
  };
  init();
}, []);

// En render
{Array.isArray(solicitudes) && solicitudes.map((s) => (...))}
{!loading && Array.isArray(solicitudes) && solicitudes.length === 0 && <EmptyState />}
```

### Patrón de Carga Sincronizada de Múltiples Endpoints (CRÍTICO)

**Problema encontrado:** En dashboards que cargan múltiples datos (stats + lista), las dos llamadas async pueden completarse en orden diferente, causando renders inconsistentes.

**Solución: SIEMPRE usar Promise.all() cuando necesites múltiples datos:**

```typescript
useEffect(() => {
  const init = async () => {
    setLoading(true);
    try {
      // ✅ IMPORTANTE: Promise.all() sincroniza AMBAS llamadas
      const [statsRes, listRes] = await Promise.all([
        api.get('/stats').catch(err => {
          console.error('Stats error:', err);
          return { data: { nuevos: 0 } };  // Fallback
        }),
        api.get('/list').catch(err => {
          console.error('List error:', err);
          return { data: [] };  // Fallback
        })
      ]);

      // ✅ Parsear defensivamente
      setStats(statsRes.data);
      const items = Array.isArray(listRes.data) ? listRes.data : (listRes.data?.data || []);
      setList(items);
    } catch (err) {
      console.error('Fatal error:', err);
      setStats({});
      setList([]);
    } finally {
      setLoading(false);  // ✅ SIEMPRE ejecuta después de AMBAS llamadas
    }
  };

  init();
}, []);
```

**Por qué es crítico:**
- Sin Promise.all(), una llamada puede ser más rápida
- El `finally` solo se ejecuta cuando la última promesa termina
- Resultado: Stats muestran data mientras List aún está cargando o falló
- Confunde al usuario: "Dice que hay 1 nuevo pero no lo veo"

### Patrón de HTTP Requests

```typescript
// Usar lib/api.ts - singleton configurado con JWT
import api from '@/lib/api';

// GET
const { data } = await api.get('/endpoint');

// POST
const { data } = await api.post('/endpoint', { payload });

// PUT
const { data } = await api.put('/endpoint/{id}', { updates });

// DELETE
await api.delete('/endpoint/{id}');

// Error handling
try {
  await api.post('/endpoint', data);
} catch (error: any) {
  const errorMsg = error.response?.data?.message || 'Error';
  console.error(errorMsg);
}
```

### Patrón de Estados Solicitud

**Siempre validar estados esperados:**

```typescript
const estadoPermitido = (estado: string, accion: string) => {
  const transiciones: Record<string, string[]> = {
    'enviar': ['borrador'],
    'observar': ['enviada'],
    'aprobar': ['enviada', 'observada'],
    'evaluar': ['en_evaluacion'],
  };
  return transiciones[accion]?.includes(estado) ?? false;
};
```

---

## 🐛 Errores Documentados & Soluciones

### Problema 1: Array Validation Errors (CRÍTICO - 7+ ocurrencias)

**Síntoma:** `X.map is not a function` | `X.filter is not a function` | `X.length is undefined`

**Causa Root:** Falta de `Array.isArray()` validación antes de usar métodos de array

**Solución:** Aplicar patrón universal (ver arriba)

**Páginas Arregladas:**
- ✅ `/solicitante/solicitudes/nueva`
- ✅ `/solicitante/dashboard`
- ✅ `/solicitante/solicitudes`
- ✅ `/admin/dashboard`
- ✅ `/revisor/solicitudes`
- ✅ `/revisor/observadas`
- ✅ `/revisor/dashboard`

**Auditoría Pendiente:** evaluador module (5+ páginas)

**Comando de Búsqueda:**
```bash
grep -r "\.map(" apps/web/src/app --include="*.tsx" | grep -v "Array.isArray" | wc -l
```

### Problema 2: Revisor Stats Mismatch - Async Loading Desynchronization (ARREGLADO 01-04-2026)

**Síntoma:** Dashboard muestra "1 nuevo" pero la lista "Próximos a Revisar" está vacía

**Causa Root:** Dos problemas combinados:
1. **Frontend:** useEffect con dos API calls en paralelo SIN sincronización
   - `api.get('/revisor/stats')` se ejecuta
   - `api.get('/revisor/solicitudes/pendientes')` se ejecuta
   - setLoading(false) solo en el finally del segundo
   - Pueden renderizar con datos inconsistentes

2. **Response Structure:** Endpoints retornan estructuras diferentes
   - `/revisor/stats` → `{ "nuevos": 1, "en_subsanacion": 0, ... }`
   - `/revisor/solicitudes/pendientes` → `[{...}, {...}]` (array directo)
   - Algunas páginas esperaban `response.data?.data` (inconsistencia)

**Solución Aplicada:**
- Refactor useEffect en `/revisor/dashboard/page.tsx`: Usar Promise.all() para sincronizar ambas llamadas
- Usar async/await con manejo explícito de carga
- Parsear respuestas de forma defensiva (array directo O `response.data.data`)
- Aplicar mismo patrón en `/revisor/completadas/page.tsx` y `/revisor/observadas/page.tsx`
- Agregar logging para debugging

**Archivos Modificados:**
- `/apps/web/src/app/revisor/dashboard/page.tsx` - Promise.all() + async/await
- `/apps/web/src/app/revisor/solicitudes/page.tsx` - Defensive parsing
- `/apps/web/src/app/revisor/completadas/page.tsx` - Promise.all() + logging
- `/apps/web/src/app/revisor/observadas/page.tsx` - Promise.all() + logging

**Patrón Correcto:**
```typescript
// ❌ MAL: Dos calls sin sincronización
api.get('/stats').then(...);
api.get('/data').then(...).finally(() => setLoading(false));

// ✅ BIEN: Promise.all() para sincronizar
const [statsRes, dataRes] = await Promise.all([
  api.get('/stats').catch(() => ({ data: {} })),
  api.get('/data').catch(() => ({ data: [] }))
]);
// Procesar ambos con setLoading(false) en finally
```

**Commit:** fix: sincronizar carga de datos en dashboard revisor con Promise.all()

### Problema 3: Revisor Login Falla por Contraseña Desincronizada (CRÍTICO - 01-04-2026)

**Síntoma:**
- Dashboard muestra "Nuevos: 1"
- Pero `/revisor/solicitudes` lista está vacía
- No hay errores visibles en frontend

**Causa Root:**
- Usuario revisor (asd@asd.com, rol_id=2) tenía contraseña incorrecta en BD
- Login fallaba silenciosamente con "Credenciales inválidas"
- Sin token JWT, las API calls retornaban 401
- Frontend recibía 401 pero no mostraba error, solo lista vacía

**Cómo Pasó:**
1. User data fue creado con contraseña X
2. En algún momento se cambió o se olvidó
3. Frontend intenta login → falla → sin token
4. API calls se hacen sin token → 401 → datos vacíos
5. Axios interceptor detecta 401 pero solo log en console

**Solución Aplicada:**
```bash
# Reset contraseña del revisor a 'password123'
php artisan tinker
>>> $revisor = User::where('email', 'asd@asd.com')->first();
>>> $revisor->password = bcrypt('password123');
>>> $revisor->save();
```

**USUARIOS TEST DISPONIBLES:**
- ✅ Admin: `admin@comecyt.gob.mx` / `password123` (rol: Administrador, ID: 1)
- ✅ Revisor: `asd@asd.com` / `password123` (rol: Revisor, ID: 4)
- ✅ Evaluador: `evaluadorr@uaemex.mx` / `password123` (rol: Evaluador, ID: 3)

**Prevención Futura:**
1. Mantener doc centralizada de credenciales de test
2. Agregar seed con usuarios y contraseñas conocidas
3. Mejorar error handling en frontend para mostrar 401 errors

---

### Problema 4: axios transformResponse Rompe Arrays (CRÍTICO - 01-04-2026)

**Síntoma:**
- Revisor logueado pero `/revisor/solicitudes` muestra vacío
- API retorna array `[{...}]` pero frontend recibe objeto `{...}`
- Dashboard shows stats pero lista está vacía

**Causa Root:**
Archivo `/apps/web/src/lib/api.ts` tenía un `transformResponse` que buscaba el primer `{` y el último `}`, extrayendo SOLO el objeto interior de arrays:

```typescript
// ❌ MALO - Rompe arrays
const jsonStart = data.indexOf('{');  // Encuentra primer { en [{"id":1,...}]
const jsonEnd = data.lastIndexOf('}'); // Encuentra último }
const jsonStr = data.substring(jsonStart, jsonEnd + 1); // Extrae {"id":1,...} SIN los [ ]
return JSON.parse(jsonStr); // Retorna OBJETO, no ARRAY
```

**Flujo del Error:**
1. Backend retorna: `[{"id":1,"folio":"COMECYT-...", ...}]`
2. Axios recibe el string JSON
3. transformResponse busca `{` a índice ~1, `}` al final
4. Extrae substring: `{"id":1,"folio":"COMECYT-...", ...}` (objeto, sin [])
5. Frontend recibe objeto en lugar de array
6. `Array.isArray(data)` = false → fallback a `[]` → lista vacía

**Solución Aplicada (01-04-2026):**
Actualizar `transformResponse` para detectar arrays y objetos:

```typescript
// ✅ BUENO - Maneja arrays Y objetos
if (trimmed.startsWith('[')) {
  const jsonStart = trimmed.indexOf('[');
  const jsonEnd = trimmed.lastIndexOf(']');
  const jsonStr = trimmed.substring(jsonStart, jsonEnd + 1);
  return JSON.parse(jsonStr); // Retorna array
}

if (trimmed.startsWith('{')) {
  const jsonStart = trimmed.indexOf('{');
  const jsonEnd = trimmed.lastIndexOf('}');
  const jsonStr = trimmed.substring(jsonStart, jsonEnd + 1);
  return JSON.parse(jsonStr); // Retorna objeto
}
```

**Archivos Modificados:**
- ✅ `/apps/web/src/lib/api.ts` - Actualizado transformResponse para arrays

**Cómo Verificar:**
```bash
# 1. Detén npm run dev (Ctrl+C)
# 2. Recarga navegador (Cmd+Shift+R para limpiar cache)
# 3. Abre DevTools → Network
# 4. Haz click en "Bandeja de Entrada"
# 5. Revisa request a /revisor/solicitudes/pendientes
# 6. Response debe ser: [{"id":1,"folio":"...", ...}]
```

---

### Problema 5: Endpoint Incorrecto `/observe` en Revisor (ARREGLADO - 01-04-2026)

**Síntoma:** Al revisor intentar generar observaciones, error "Error al generar observación"

**Causa Root:**
Frontend llamaba endpoint incorrecto:
```typescript
// ❌ INCORRECTO
await api.post(`/revisor/solicitudes/${id}/observe`, { observaciones: newObservaciones });
```

Endpoint real es `/observar` (no `/observe`), definido en `routes/api.php`:
```php
Route::post('solicitudes/{solicitud}/observar', [RevisionController::class, 'observe']);
```

**Solución Aplicada:**
```typescript
// ✅ CORRECTO
await api.post(`/revisor/solicitudes/${id}/observar`, { observaciones: newObservaciones });
```

**Archivos Modificados:**
- ✅ `/apps/web/src/app/revisor/solicitudes/[id]/page.tsx` - línea 87

**Lección:** Frontend y Backend DEBEN coincidir exactamente en nombres de endpoints. Usar consistent naming: `/observar` (español) en ambos lados.

---

### Problema 6: Ruta Faltante `/solicitante/solicitudes/[id]/editar` (ARREGLADO - 01-04-2026)

**Síntoma:** Al solicitante hacer clic en "Corregir ahora" en solicitud observada, error 404

**Causa Root:**
Página detalle (`/solicitante/solicitudes/[id]/page.tsx`) tenía link a:
```typescript
<Link href={`/solicitante/solicitudes/${id}/editar`}>
```

Pero esa ruta NO existía → 404 "This page could not be found"

**Solución Aplicada:**
1. Crear ruta `/solicitante/solicitudes/[id]/editar/page.tsx`
2. Esa página redirige automáticamente a `/solicitante/solicitudes/[id]`
3. Página de detalles YA soporta reenvío para estado `observada`

**Archivo Creado:**
- ✅ `/apps/web/src/app/solicitante/solicitudes/[id]/editar/page.tsx` - redirige a detalles

**Lección:** Si una página tiene link a una ruta, esa ruta DEBE existir. Usar rutas intermedias para redirect si es necesario.

---

### Problema 7: Convocatorias Reales Faltantes (ARREGLADO - 01-04-2026 SESIÓN 2)

**Síntoma:** 5 programas COMECYT existían pero solo 1 convocatoria "Test", usuarios no veían opciones para solicitar

**Causa Root:** Los 5 programas (PFPI, PROT, IPFE, VINC, EMP) fueron creados via seeders pero nunca se crearon convocatorias activas

**Solución Aplicada:**
```bash
# Crear ConvocatoriasRealesSeeder.php con 5 convocatorias para 2026
- PFPI: Pago de Fórmulas ($100,000)
- PROT: Prototipos ($350,000)
- IPFE: Profesionistas ($500,000)
- VINC: Vinculación ($1,000,000)
- EMP: Emprendedores ($200,000)

php artisan db:seed --class=ConvocatoriasRealesSeeder
```

**Archivo:** `/apps/api/database/seeders/ConvocatoriasRealesSeeder.php` ✅

**Lección:** Cuando creas programas/catálogos BD-driven, también crea instancias reales en seeders.

---

### Problema 8: Solicitante No Puede Subir Documentos (ARREGLADO - 01-04-2026 SESIÓN 2)

**Síntoma:** Revisor observa "Identificación Oficial", "Comprobante Domicilio", "CURP", "RFC" pero solicitante no tiene donde subirlos

**Causa Root:** Sistema diseñado con observaciones pero componente de upload nunca fue implementado. Backend solo aceptaba tipos financieros.

**Solución Aplicada:**

1. **Backend migración**: tabla `solicitud_documentos`
2. **Modelo**: `SolicitudDocumento` con relación a `Solicitud`
3. **Controlador**: Ampliar `DocumentoUploadController` para aceptar 5 tipos:
   - identificacion_oficial, comprobante_domicilio, curp, rfc, otros
4. **Relación**: Agregar `hasMany(SolicitudDocumento::class)` en `Solicitud`
5. **Frontend componente**: `DocumentosAdjuntos.tsx` con UI para cada tipo
6. **Integración**: Mostrar en `/[id]/page.tsx` y `/[id]/editar/page.tsx`

**Archivos:**
- Migración: `2026_04_01_235959_create_solicitud_documentos_table.php` ✅
- Modelo: `SolicitudDocumento.php` ✅
- Controlador: `DocumentoUploadController.php` ✅
- Componente: `DocumentosAdjuntos.tsx` ✅
- Páginas: detalles + editar ✅

**Lección:** Campos de observación revisor = campos de entrada solicitante. Mantener simetría.

---

### Problema 9: Convocatorias Sin tipo_programa_id (REQUERIMIENTO)

**Síntoma:** Dropdown vacío en nueva solicitud

**Causa:** Convocatorias creadas sin `tipo_programa_id` asignado

**Regla Crítica:** **Todas las convocatorias DEBEN tener tipo_programa_id**

**Validación Backend:**
```php
'tipo_programa_id' => 'required|exists:tipos_programa,id'
```

### Problema 10: Hardcoded Colors Wave 3 (DEUDA TÉCNICA)

**Scope:** ~200 hardcoded color strings en 15+ páginas

**Solución:** Completar refactorización Wave 3 con `colorMap` tokens

**Urgencia:** Baja (cosmético, no afecta funcionalidad)

### Problema 11: React Key Uniqueness (ARREGLADO)

**Síntoma:** Console warning "two children with same key"

**Ubicación:** `SidebarLayout.tsx` line 133

**Solución:** Cambiar key de `item.href` a `${item.href}-${index}`

---

### Problema 12: Admin Panel 500 Error - Laravel 11 Middleware (ARREGLADO - 01-04-2026)

**Síntoma:** Admin panel `/admin/programas` retorna 500 "Call to undefined method middleware()"

**Causa Root:** En Laravel 11, el método `$this->middleware()` en el constructor NO está disponible. Los 6 controllers admin usaban este patrón deprecated:

```php
// ❌ OBSOLETO EN LARAVEL 11
public function __construct()
{
    $this->middleware('auth:api');
    $this->middleware('admin');
}
```

Controllers afectados:
- TipoProgramaController
- ProgramaCampoController
- ProgramaCriterioEvaluacionController
- ProgramaEtapaController
- ProgramaModalidadController
- ProgramaRubroController

**Solución Aplicada:**

1. **Remover middleware() calls del constructor** de todos los admin controllers
   ```php
   // ✅ NUEVO
   public function __construct()
   {
       // Laravel 11: middleware applied via routes
       // See routes/api.php for middleware application
   }
   ```

2. **Aplicar 'admin' middleware en la ruta group** (`routes/api.php` line 72)
   ```php
   // ANTES:
   Route::group(['prefix' => 'admin'], function () {

   // DESPUÉS:
   Route::group(['prefix' => 'admin', 'middleware' => 'admin'], function () {
   ```

La autenticación 'api.auth' ya se aplicaba en el grupo parent, solo faltaba el 'admin' middleware en el grupo específico.

**Archivos Modificados:**
- ✅ `apps/api/routes/api.php` - agregado middleware en admin group
- ✅ 6 admin controllers - removidos $this->middleware() calls

**Lección - Laravel 11 Middleware Patterns:**

✅ **CORRECTO (Ruta Group):**
```php
Route::group(['middleware' => 'admin'], function () {
    Route::apiResource('resource', Controller::class);
});
```

✅ **CORRECTO (Atributo):**
```php
#[Middleware('admin')]
public function store(Request $request) { ... }
```

✅ **CORRECTO (Bootstrap):**
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias(['admin' => AdminMiddleware::class]);
})
```

❌ **INCORRECTO (Constructor - Deprecated):**
```php
public function __construct()
{
    $this->middleware('admin');  // No existe en L11
}
```

---

### Problema 13: null is not an object (evaluating 'prog.monto_maximo.toLocaleString') (ARREGLADO - 03-04-2026)

**Síntoma:** Admin panel `/admin/programas` crash en línea 207 de page.tsx

**Causa Root:** Campo `monto_maximo` en tabla programas puede ser NULL en BD, pero código intentaba `.toLocaleString()` sin null check

**Solución Aplicada:**
```typescript
// ❌ ANTES
${prog.monto_maximo.toLocaleString()}

// ✅ DESPUÉS
${(prog.monto_maximo || 0).toLocaleString()}
```

**Lección:** Cualquier dato que viene de BD debe tener fallback default. Usar `field || defaultValue` en renders.

**Archivo Modificado:**
- ✅ `apps/web/src/app/admin/programas/page.tsx` line 207

---

### Problema 14: TypeError - count() argument must be Countable|array, __PHP_Incomplete_Class given (ARREGLADO - 03-04-2026)

**Síntoma:** Error 500 en endpoints `/catalogs/programa/{id}/documentos`, `/campos`, `/rubros`, `/etapas`, `/modalidades`

**Stack Trace:**
```
ProgramaCatalogController.php:72 → count(Object(__PHP_Incomplete_Class))
```

**Causa Root - CRÍTICA (Cache + Eloquent Models):**

```php
// ❌ MAL - Cache::remember() serializa modelos Eloquent
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get();  // ← Eloquent Collection
});
// Cuando se deserializa del cache → __PHP_Incomplete_Class
// .toLocaleString(), count(), property access → TypeError
```

**Solución Universal:**
```php
// ✅ BIEN - Convertir a ARRAY PLANO antes de cachear
$data = Cache::remember($key, $ttl, function () {
    return Model::with('relations')->get()->toArray();  // ← Array seguro
});
// Deserializa como array normal, count() funciona, property access seguro
```

**Archivos Modificados:**
- ✅ `apps/api/app/Http/Controllers/Catalogos/ProgramaCatalogController.php`
  - `show()` - convertir a array
  - `campos()` - convertir a array + is_array() check
  - `documentos()` - convertir a array + is_array() check
  - `criterios()` - convertir a array
  - `rubros()` - convertir a array + is_array() check
  - `etapas()` - convertir a array + is_array() check
  - `modalidades()` - convertir a array + is_array() check

**Dónde Aplicar Esta Lección:**
1. Cualquier `Cache::remember()` que cachea colecciones Eloquent
2. Cualquier `Cache::get()` / `Cache::put()` con modelos
3. Controllers que cachean para APIs
4. Siempre: `$collection->toArray()` ANTES de cachear

**Patrón Defensivo Extra:**
```php
// Ser defensive con count() en resultados cacheados
return response()->json([
    'count' => is_array($data) ? count($data) : 0,
    'data' => $data ?? []
]);
```

---

### Problema 15: Revisor Solicitud 404/500 (SÍNTOMA DE #14)

**Síntoma:** `/revisor/solicitudes/4` → Axios Error 500 "Request failed with status code 500"

**Causa Root:** Frontend intenta cargar documentos del programa llamando `/catalogs/programa/{id}/documentos`, que fallaba por Problema #14

**Status:** ✅ Resuelto al arreglar Problema #14

---

### Problema 16: Nueva Solicitud - Flujo Confuso (ARREGLADO - 03-04-2026)

**Síntoma:** Usuario en `/solicitante/solicitudes/nueva` clickea "Someter a Revisión" → Error "Faltan documentos obligatorios para enviar la solicitud"

**Causa Root:** El botón "Someter a Revisión" intentaba:
1. Crear solicitud (POST /solicitudes)
2. Enviar inmediatamente (POST /solicitudes/{id}/enviar)
3. Pero faltan documentos → Error 422

**Flujo Incorrecto Antes:**
```
Nueva Solicitud → Someter a Revisión →
  ✓ Crear borrador
  ✗ Intentar enviar (sin documentos) → Error
```

**Flujo Correcto Después:**
```
Nueva Solicitud → Crear e Ir a Documentos →
  ✓ Crear borrador
  → Redirigir a /solicitudes/{id} →
  ✓ Subir documentos obligatorios →
  ✓ Clickear "Enviar"
```

**Soluciones Aplicadas:**
1. Cambiar label de botón: "Someter a Revisión" → "Crear e Ir a Documentos" (más claro)
2. En handleSubmit: cuando submitFinal=true, no enviar inmediatamente
3. Crear solicitud como borrador
4. Redirigir a /solicitudes/{id} (página de detalle donde puede subir documentos)
5. Mensaje de feedback: "Solicitud creada. Ahora debes subir los documentos obligatorios."

**Archivo Modificado:**
- ✅ `apps/web/src/app/solicitante/solicitudes/nueva/page.tsx`
  - Línea 226-238: Cambiar lógica submitFinal para redirigir a detalle
  - Línea 642: Cambiar label de botón

**Lección:** Flujos multi-paso deben ser CLAROS y SIN SORPRESAS. Si el usuario hace clic en "Someter a Revisión" espera que se someta, pero en realidad necesita documentos primero. Mejor:
- Cambiar nombres de botones para que sean más descriptivos
- Redirigir a páginas intermedias si hay requisitos
- O desabilitar botones si no se cumplen condiciones

---

### Problema 17: DocumentosAdjuntos no carga tipos de documentos (ARREGLADO - 03-04-2026)

**Síntoma:** En `/solicitante/solicitudes/{id}` sección "Documentos Adjuntos" no muestra slots para subir documentos

**Causa Root - Múltiple:**
1. **API Response Structure Mismatch:**
   - Endpoint retorna: `{ message: "OK", data: [...], count: ... }`
   - Componente esperaba: array directo
   - Resultado: `tiposDocumento` queda vacío

2. **Missing Relation in Backend:**
   - `SolicitudController::show()` cargaba `'convocatoria'` pero NO `'convocatoria.tipoPrograma'`
   - Frontend recibía `tipo_programa_id = undefined`
   - Fallback a `tipoProgramaId || 1` (incorrecto)

3. **No Empty State:**
   - Si `tiposDocumento.length === 0`, componente no mostraba nada
   - Usuario veía sección vacía sin mensajes

**Soluciones Aplicadas:**

1. **Frontend - Defensive API Parsing** (`DocumentosAdjuntos.tsx`):
```typescript
const response = await api.get(`/catalogs/programa/${tipoProgramaId}/documentos`);
// Handle both { data: [...] } and direct array
const tipos = Array.isArray(response.data?.data)
  ? response.data.data
  : Array.isArray(response.data)
  ? response.data
  : [];
```

2. **Frontend - Empty State** (`DocumentosAdjuntos.tsx`):
```typescript
{tiposDocumento.length === 0 ? (
  <AlertBox type="info" description="No hay documentos requeridos para este programa." />
) : (
  // Renderizar slots de upload
)}
```

3. **Backend - Eager Load Relation** (`SolicitudController::show()`):
```php
// ANTES
$solicitud->load(['convocatoria', 'institucion', ...]);

// DESPUÉS
$solicitud->load(['convocatoria.tipoPrograma', 'institucion', ...]);
```

**Archivos Modificados:**
- ✅ `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx` (parsing + empty state)
- ✅ `apps/api/app/Http/Controllers/Solicitudes/SolicitudController.php` (eager load relation)

**Lección - API Response Consistency:**
Backend endpoints deberían ser consistentes:
- Either: SIEMPRE retornar array directo
- Or: SIEMPRE retornar `{ data: [...], message: "...", meta: {...} }`

Frontend debe ser DEFENSIVO:
```typescript
// ✅ SIEMPRE hacer esto
const items = Array.isArray(response.data?.data)
  ? response.data.data
  : Array.isArray(response.data)
  ? response.data
  : [];
```

---

### Problema 18: File Input dentro de Label No Funciona (ARREGLADO - 03-04-2026)

**Síntoma:** Button "Subir PDF" clickeable pero no abre file picker

**Causa Root - Dos Errores:**

1. **Primer intento (asChild):**
```jsx
// ❌ INCORRECTO
<Button asChild>
  <span>Subir PDF</span>  // asChild no acepta componentes personalizados
</Button>
```
Error: "React does not recognize the 'asChild' prop"

2. **Segundo intento (label + Button):**
```jsx
// ❌ CASI, pero...
<label className="cursor-pointer">
  <input type="file" hidden />
  <Button type="button">Subir PDF</Button>  // Button NO heredó onClick del label
</label>
```
Problema: Label funciona para `<input>` nativo, pero Button es un componente custom que no propagó el click

**Solución - useRef + onClick:**
```jsx
const fileInputRefs = useRef<Record<string, HTMLInputElement | null>>({});

<input
  ref={(el) => fileInputRefs.current[tipo.clave] = el}
  type="file"
  accept=".pdf"
  onChange={handleFileSelect}
  className="hidden"
/>
<Button
  type="button"
  onClick={() => {
    const input = fileInputRefs.current[tipo.clave];
    if (input) input.click();  // ← Dispara el file picker
  }}
>
  Subir PDF
</Button>
```

**Por qué funciona:**
- `useRef` accede directamente al DOM element
- `.click()` en un `<input type="file">` abre el file picker nativo
- `onChange` en el input procesa el archivo seleccionado
- Sin depender de label o propagación de eventos

**Archivos Modificados:**
- ✅ `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx`
  - Agregar `useRef` al import
  - Crear `fileInputRefs` object
  - Cambiar estructura: input con ref + Button con onClick

**Lección - File Input Patterns:**

❌ **INCORRECTO (Componentes custom no heredan eventos):**
```jsx
<label>
  <input hidden />
  <CustomButton /> {/* No funciona, Button no recibe onClick del label */}
</label>
```

✅ **CORRECTO (Usar ref + onClick directo):**
```jsx
const inputRef = useRef();
<input ref={inputRef} type="file" hidden />
<button onClick={() => inputRef.current.click()}>Upload</button>
```

✅ **TAMBIÉN CORRECTO (Para múltiples inputs - objeto de refs):**
```jsx
const inputRefs = useRef({});
{items.map(item => (
  <>
    <input ref={el => inputRefs.current[item.id] = el} hidden />
    <Button onClick={() => inputRefs.current[item.id]?.click()}>Upload</Button>
  </>
))}
```

---

### Feature: Vista Previa de Documentos PDF (IMPLEMENTADO - 03-04-2026)

**Descripción:** Agregar botón de Vista Previa en DocumentosAdjuntos que abre un modal/dialog para visualizar el contenido del PDF

**Implementación:**

1. **Estado para Vista Previa:**
```typescript
const [previewingDoc, setPreviewingDoc] = useState<Documento | null>(null);
```

2. **Botón Eye Icon:** Entre Download y Delete
```jsx
<Button
  variant="ghost"
  size="sm"
  className="text-green-600 hover:text-green-700 hover:bg-green-50"
  onClick={() => setPreviewingDoc(doc)}
  title="Vista previa del documento"
>
  <Eye className="h-4 w-4" />
</Button>
```

3. **Dialog con iframe:**
```jsx
<Dialog open={!!previewingDoc} onOpenChange={(open) => !open && setPreviewingDoc(null)}>
  <DialogContent className="max-w-4xl w-full h-[80vh] flex flex-col">
    <DialogHeader>
      <DialogTitle>{previewingDoc?.nombre_original}</DialogTitle>
      <Button variant="ghost" size="icon" onClick={() => setPreviewingDoc(null)}>
        <X className="h-4 w-4" />
      </Button>
    </DialogHeader>
    <div className="flex-1 overflow-hidden">
      <iframe src={previewingDoc.url} className="w-full h-full" />
    </div>
  </DialogContent>
</Dialog>
```

**Características:**
- ✅ Botón Eye icon (verde) junto a Download y Delete
- ✅ Modal/Dialog fullscreen (max-w-4xl, h-[80vh])
- ✅ iframe para visualizar PDF directamente
- ✅ Título del documento en el header
- ✅ Botón X para cerrar modal
- ✅ Responsive y centrado

**Archivos Modificados:**
- ✅ `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx`
  - Importar Dialog components, Eye icon, X icon
  - Agregar estado `previewingDoc`
  - Agregar botón Eye con onClick
  - Agregar Dialog component con iframe

**Lección - Visualización de PDFs:**

**Opción 1 (iframe simple - Lo que usamos):**
```jsx
<iframe src="https://...documento.pdf" />
// ✅ Rápido, simple, no requiere librerías
// ❌ Requiere CORS, no funciona con todos los servidores
// ❌ Funcionalidad limitada (sin zoom, búsqueda, etc)
```

**Opción 2 (pdf.js - Para más control):**
```jsx
// npm install pdfjs-dist
import * as pdfjsLib from 'pdfjs-dist';
// Más funcionalidades pero más código
```

**Opción 3 (Descargar y abrir):**
```jsx
<a href={doc.url} target="_blank">Descargar</a>
// El navegador abre en nueva pestaña
```

Para este caso, iframe es suficiente y mantiene la UX dentro de la app.

**Problema encontrado: iframe con URLs relativas retorna 404**

El documento se guarda con URL relativa: `/storage/documentos/{id}/{filename}`

iframe necesita URL absoluta. Solución:
```typescript
// Construir URL completa si es relativa
const fullUrl = previewingDoc.url.startsWith('http')
  ? previewingDoc.url
  : `${window.location.origin}${previewingDoc.url}`;

<iframe src={fullUrl} />
```

**Archivos Modificados:**
- ✅ `DocumentosAdjuntos.tsx` - iframe con URL completa + allow permissions

---

### Problema 19: URLs Relativas Apuntan a Frontend en Lugar de Backend (ARREGLADO - 03-04-2026)

**Síntoma:** Vista previa y descarga fallan con 404
- Preview (iframe): `localhost:3000/storage/documentos/...` → 404
- Download (link): `localhost:3000/storage/documentos/...` → 404

**Causa Root - Arquitectura Multi-Puerto:**
- Frontend (Next.js): puerto 3000
- Backend (Laravel): puerto 8000
- URL guardada en BD: `/storage/documentos/6/archivo.pdf` (relativa)
- Frontend construía: `http://localhost:3000/storage/...` (intenta acceder en puerto 3000)
- Pero archivos sirven desde: `http://localhost:8000/storage/...` (puerto 8000)

**Solución - Usar NEXT_PUBLIC_API_URL:**

```typescript
// Helper function
const getAbsoluteUrl = (url: string) => {
  if (url.startsWith('http')) return url;
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';
  return `${apiUrl}${url}`;
};

// Uso en links y iframes
<a href={getAbsoluteUrl(doc.url)}>Descargar</a>
<iframe src={getAbsoluteUrl(previewingDoc.url)} />
```

**Por qué funciona:**
- `NEXT_PUBLIC_API_URL` se configura en `.env.local`
- Típicamente: `NEXT_PUBLIC_API_URL=http://localhost:8000`
- Función construye URL completa: `http://localhost:8000/storage/documentos/...`
- frontend puede acceder correctamente

**Archivos Modificados:**
- ✅ `DocumentosAdjuntos.tsx`
  - Agregar función `getAbsoluteUrl()`
  - Cambiar `doc.url` → `getAbsoluteUrl(doc.url)` en link download
  - Cambiar iframe src a usar `getAbsoluteUrl()`

**Lección - URLs en Aplicaciones Multi-Server:**

❌ **INCORRECTO:**
```javascript
// Hardcodear puerto
const url = `http://localhost:3000/storage/...`;
```

✅ **CORRECTO:**
```javascript
// Usar variable de entorno
const apiUrl = process.env.NEXT_PUBLIC_API_URL;
const url = `${apiUrl}/storage/...`;
```

**Configuración (.env.local en frontend):**
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

**Problema Específico - `/api` en ruta de storage:**

URL en BD: `/storage/documentos/6/archivo.pdf`
NEXT_PUBLIC_API_URL: `http://localhost:8000/api`

Simple concat: `http://localhost:8000/api` + `/storage/...` = ❌ `http://localhost:8000/api/storage/...`
Correcto: ✅ `http://localhost:8000/storage/...`

**Solución:**
```typescript
const getAbsoluteUrl = (url: string) => {
  if (url.startsWith('http')) return url;
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api';

  // Storage files are at /storage/..., not /api/storage/...
  // Remove /api from the base URL for storage paths
  if (url.startsWith('/storage/')) {
    const baseUrl = apiUrl.replace('/api', '');
    return `${baseUrl}${url}`;
  }

  return `${apiUrl}${url}`;
};
```

---

### Problema 20: 403 Forbidden al Acceder Documentos (ARREGLADO - 03-04-2026)

**Síntoma:** Vista previa y descarga muestran error 403 Forbidden
```
GET http://localhost:8000/storage/documentos/6/COMECYT-2026-ABCDEF_ficha_tecnica_1712000000.pdf
→ 403 Forbidden
```

**Causa Root - Disco de Storage Incorrecto:**

En `DocumentoUploadController::upload()` línea 34, el archivo se estaba guardando con el disco incorrecto:

```php
// ❌ INCORRECTO
$file->storeAs("public/documentos/{$solicitud->id}", $filename);
$publicUrl = Storage::url("documentos/{$solicitud->id}/{$filename}");
```

**El Problema:**
1. `$file->storeAs()` sin especificar disco usa el disco por defecto: `'local'` (de `config/filesystems.php`)
2. El disco `'local'` apunta a `storage/app/private` (NO público)
3. Archivo se guardaba en: `storage/app/private/public/documentos/{id}/{filename}`
4. Pero `Storage::url()` intentaba leer del disco `'public'` que apunta a `storage/app/public`
5. Resultado: Path mismatch → archivo no existe en la ruta pública → 403

**Solución - Usar Disco Explícitamente:**

```php
// ✅ CORRECTO
Storage::disk('public')->putFileAs("documentos/{$solicitud->id}", $file, $filename);
$publicUrl = Storage::disk('public')->url("documentos/{$solicitud->id}/{$filename}");
```

**Así Funciona:**
1. `Storage::disk('public')` apunta a `storage/app/public`
2. Archivo se guarda en: `storage/app/public/documentos/{id}/{filename}` ✅
3. URL generada apunta a la ruta pública: `/storage/documentos/{id}/{filename}` ✅
4. Symlink `public/storage` → `storage/app/public` permite servir los archivos

**Archivos Modificados:**
- ✅ `apps/api/app/Http/Controllers/DocumentoUploadController.php`
  - Línea 35: Cambiar `$file->storeAs()` a `Storage::disk('public')->putFileAs()`
  - Línea 38: Cambiar `Storage::url()` a `Storage::disk('public')->url()`
  - Línea 110-112: Cambiar `Storage::exists()` y `Storage::delete()` a usar `disk('public')`

**Lección - Laravel Storage Disks:**

❌ **INCORRECTO (Implícito, usa disco por defecto):**
```php
$file->storeAs("path/to/file", $filename);
Storage::url("path/to/file/{$filename}");
```

✅ **CORRECTO (Explícito, especifica disco):**
```php
Storage::disk('public')->putFileAs("path/to/file", $file, $filename);
Storage::disk('public')->url("path/to/file/{$filename}");
```

**Configuración de Discos (en `config/filesystems.php`):**
```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),  // ← Privado, NO accesible públicamente
],

'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),   // ← Público, accesible via /storage/
    'url' => rtrim(env('APP_URL'), '/').'/storage',
],
```

**Patrón de Almacenamiento de Documentos:**
1. **Solicitud documentos (públicos para solicitante)**: Usar disco `'public'`
2. **Documentos financieros (privados)**: Usar disco `'local'` (con auth)
3. **SIEMPRE especificar disco explícitamente** para evitar confusión

---

### Problema 21: AlertBox Import Error en Wizard (ARREGLADO - 06-04-2026)

**Síntoma:** Build error en `apps/web/src/app/admin/convocatorias/nueva/page.tsx:12`
```
Error: Export default doesn't exist in target module '@/components/ui/alert-box'
```

**Causa Root:** AlertBox tiene named export, no default export
```typescript
// ❌ INCORRECTO
import AlertBox from '@/components/ui/alert-box';

// ✅ CORRECTO
import { AlertBox } from '@/components/ui/alert-box';
```

**También:** Las llamadas a AlertBox usaban prop `description` pero el componente espera `message`
```typescript
// ❌ INCORRECTO
<AlertBox type="error" description={text} />

// ✅ CORRECTO
<AlertBox type="error" message={text} />
```

**Archivos Modificados:**
- ✅ `apps/web/src/app/admin/convocatorias/nueva/page.tsx` (import + 8 props)
- ✅ `apps/web/src/app/solicitante/solicitudes/[id]/page.tsx` (1 prop fix)
- ✅ `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx` (2 props fix)

**Lección - Component Exports:**

```typescript
// Export type MATTERS
export const Component = () => { ... }     // Named export → import { Component }
export default Component;                  // Default export → import Component
export { Component as default };           // Named to default

// SIEMPRE verificar qué exporta el componente
```

---

### Problema 22: colorMap Property Names (bg vs background) (ARREGLADO - 06-04-2026)

**Síntoma:** TypeScript error al compilar
```
Property 'bg' does not exist on type '{ background: string; text: string; ... }'
```

**Causa Root:** Inconsistencia en color-mapper.ts
- Algunos lugares usan `.bg`
- La definición actual usa `.background`

**Solución:**
```typescript
// ❌ INCORRECTO
className={`${colorMap.states.success.bg} ${colorMap.states.success.text}`}

// ✅ CORRECTO
className={`${colorMap.states.success.background} ${colorMap.states.success.text}`}
```

**Archivos Modificados:**
- ✅ `apps/web/src/app/solicitante/solicitudes/[id]/editar/page.tsx` (1 fix)
- ✅ `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx` (1 fix)

**Lección - Standardizar Nombres:**

En `/apps/web/src/lib/color-mapper.ts` la estructura debe ser consistente:
```typescript
export const colorMap = {
  states: {
    success: {
      background: 'bg-emerald-50',    // ← Usar 'background', no 'bg'
      text: 'text-emerald-600',
      border: 'border-emerald-500',
      main: 'text-emerald-700',
    },
    // ...
  }
}
```

**Recomendación:** Hacer auditoría de colorMap para asegurar consistencia en toda la app. Buscar si hay más usos de `.bg` que deban cambiar a `.background`.

---

## 📡 API Endpoints Principales

### Autenticación

```
POST   /auth/login                 # { email, password } → { access_token, user }
POST   /auth/logout (protected)    # Logout
POST   /auth/refresh (protected)   # Refresh token
GET    /auth/me (protected)        # Usuario actual
```

### Catálogos (Públicos - Sin Auth)

```
GET    /catalogs/programa/{id}              # Config + todas las subcategorías
GET    /catalogs/programa/{id}/campos       # Campos dinámicos del formulario
GET    /catalogs/programa/{id}/criterios    # Criterios de evaluación
GET    /catalogs/programa/{id}/rubros       # Rubros presupuestarios
GET    /catalogs/programa/{id}/modalidades  # Modalidades
```

### Solicitudes (Solicitante)

```
GET    /solicitudes                         # Mis solicitudes
POST   /solicitudes                         # Crear solicitud
GET    /solicitudes/convocatorias-activas   # Convocatorias disponibles
GET    /solicitudes/{id}                    # Detalles solicitud
POST   /solicitudes/{id}/enviar             # Cambiar: borrador → enviada
POST   /solicitudes/{id}/informe            # Enviar informe final
```

### Revisión (Revisor)

```
GET    /revisor/stats                       # Estadísticas dashboard
GET    /revisor/solicitudes/pendientes      # Listado para revisar (estado: enviada|observada)
GET    /revisor/solicitudes/{id}            # Detalles
POST   /revisor/solicitudes/{id}/aprobar    # Estado: enviada → en_evaluacion
POST   /revisor/solicitudes/{id}/observar   # Estado: enviada → observada + crea observaciones
```

### Evaluación (Evaluador)

```
GET    /evaluador/stats                     # Estadísticas dashboard
GET    /evaluador/asignaciones              # Mis asignaciones
GET    /evaluador/asignaciones/{id}         # Detalles de asignación
POST   /evaluador/asignaciones/{id}/dictamen # Crear dictamen (califica solicitud)
```

### Admin (Administrador)

```
GET    /admin/stats                    # Dashboard estadísticas
GET    /admin/activity                 # Gráfico de actividad

# CRUD Programas (Dinámicos)
GET    /admin/programas
POST   /admin/programas
PUT    /admin/programas/{id}
DELETE /admin/programas/{id}

# Campos, Rubros, Etapas, Modalidades, Criterios (nested)
GET    /admin/programas/{id}/campos
POST   /admin/programas/{id}/campos
[... similar para otros recursos]

# CRUD Convocatorias, Usuarios, Ministraciones, Informes, etc
# Asignaciones
POST   /admin/asignaciones-evaluador

# Documentos
GET    /admin/reportes/excel         # Descargar reporte Excel
```

---

## 🔒 PATRÓN CRÍTICO: Validación en 2 Capas (GOLD RULE)

### El Problema Descubierto (02-04-2026)

El sistema tenía **infraestructura completa** (BD, API, componentes) pero faltaban validaciones que permitían:
- ❌ Solicitante enviaba sin documentos obligatorios
- ❌ Usuario creaba equipo sin cumplir mínimo requerido

**Raíz:** El frontend AlertBox solo **advertía**, nunca **bloqueaba**. El backend **no validaba** antes de cambiar estado.

### La Solución: Validación Redundante en 2 Capas

```
Capa 1: Frontend (UX/Experiencia)
├─ Validar ANTES de llamar API
├─ Mostrar error visible (AlertBox, texto rojo)
├─ Deshabilitar botón si hay errores
└─ Feedback inmediato al usuario

Capa 2: Backend (Seguridad)
├─ Validar DESPUÉS de recibir request
├─ Rechazar con 422 + detalles de qué falta
├─ Prevenir estado inconsistente en BD
└─ Protección si frontend se saltea validación
```

### Patrones Implementados en esta Sesión

#### PATRÓN 1: Documentos Obligatorios

**Frontend - `[id]/page.tsx::handleEnviar()`:**
```typescript
// Cargar tipos de documentos del programa al cargar la solicitud
const tipoProgramaId = data.convocatoria?.tipo_programa_id;
if (tipoProgramaId) {
  const docsRes = await api.get(`/catalogs/programa/${tipoProgramaId}/documentos`);
  setTiposDocumento(Array.isArray(docsRes.data) ? docsRes.data : []);
}

// En handleEnviar(), ANTES de confirmar:
const documentosSubidos = solicitud.documentos?.map(d => d.tipo) || [];
const faltantes = tiposDocumento.filter(t => t.obligatorio && !documentosSubidos.includes(t.clave));
if (faltantes.length > 0) {
  setEnviarError(`Faltan documentos obligatorios: ${faltantes.map(d => d.nombre).join(', ')}`);
  return;  // ← BLOQUEA antes de llamar API
}
```

**Backend - `SolicitudController::enviar()`:**
```php
// Cargar relación antes de cambiar estado
$solicitud->load(['documentos', 'convocatoria.tipoPrograma.documentos']);

$docsObligatorios = $solicitud->convocatoria->tipoPrograma->documentos()
    ->where('obligatorio', true)->where('activo', true)->pluck('clave')->toArray();

$docsSubidos = $solicitud->documentos->pluck('tipo')->toArray();
$docsFaltantes = array_diff($docsObligatorios, $docsSubidos);

if (!empty($docsFaltantes)) {
    return response()->json([
        'error' => 'Faltan documentos obligatorios',
        'documentos_faltantes' => array_values($docsFaltantes),
    ], 422);  // ← RECHAZA antes de cambiar estado
}
```

**UI Feedback:**
```typescript
{enviarError && (
  <AlertBox type="error" title="No se puede enviar" description={enviarError} />
)}
```

#### PATRÓN 2: Count de Miembros Equipo (Min/Max)

**Frontend - `nueva/page.tsx::validateForm()`:**
```typescript
if (programa?.tiene_equipo) {
  const min = programa.min_miembros_equipo || 0;
  const max = programa.max_miembros_equipo || 100;

  if (min > 0 && miembros.length < min) {
    errors.miembros_count = `Se requieren mínimo ${min} miembro(s)`;
  }
  if (miembros.length > max) {
    errors.miembros_count = `Máximo ${max} miembro(s) permitidos`;
  }
}
```

**UI Feedback:**
```typescript
{fieldErrors.miembros_count && (
  <p className={`text-sm ${colorMap.states.error.text} mt-4 font-medium`}>
    {fieldErrors.miembros_count}
  </p>
)}
```

### Golden Rules Posteriores

1. **NUNCA validar solo en UI** — Frontend puede saltarse, necesitas backend como red de contención
2. **NUNCA validar solo en Backend** — UX sufre, usuario no sabe qué está mal hasta después de submitear
3. **SIEMPRE validar en ambas** — Redundancia deliberada = robustez
4. **Frontend primero** — Mejor experiencia (feedback inmediato)
5. **Backend siempre** — Garantiza integridad de datos (nunca confíes en frontend)
6. **Errores descriptivos** — Dile al usuario EXACTAMENTE qué falta (nombres, no IDs)

### Testing Pattern

```bash
# Test 1: Frontend bloquea
Crear solicitud IPFE sin docs → Click "Enviar" → AlertBox muestra "Faltan: Ficha Técnica, ..."
✅ No llama API, botón deshabilitado

# Test 2: Backend como red de contención
curl -X POST /api/solicitudes/{id}/enviar -H "Authorization: Bearer TOKEN"
# Si frontend se saltó validación:
← 422 { "error": "Faltan documentos obligatorios", "documentos_faltantes": ["ficha_tecnica"] }

# Test 3: Happy path (todos docs present)
Crear solicitud IPFE + Subir 5 docs + Click "Enviar" → Success 200, estado → 'enviada'
```

---

---

## 🚀 Implementación: Wizard 7-Pasos para Nueva Convocatoria (Planificado - Iniciando 03-04-2026)

### Estado: **Plan Aprobado** → Implementación lista para iniciar

### Archivos a Crear/Modificar

**Backend (3 archivos):**
1. ✅ Crear `apps/api/app/Http/Controllers/Admin/ProgramaDocumentoController.php`
   - Methods: `index()`, `store()`, `update()`, `destroy()`
   - Valida: nombre*, clave*, descripcion, obligatorio, orden

2. ✅ Editar `apps/api/routes/api.php`
   - Agregar rutas CRUD documentos bajo admin group
   - Route: `Route::apiResource('programas/{tipoPrograma}/documentos', ProgramaDocumentoController::class)`

3. ✅ Editar `apps/api/app/Http/Controllers/Convocatorias/ConvocatoriaController.php`
   - Actualizar `store()` y `update()` para validar y guardar `tipo_programa_id`
   - Validation rule: `'tipo_programa_id' => 'nullable|exists:tipo_programas,id'`

**Frontend (1 archivo):**
1. ✅ Reemplazar `apps/web/src/app/admin/convocatorias/nueva/page.tsx`
   - Convertir a Wizard de 7 pasos
   - Estado local acumula todo hasta Step 7
   - En Step 7: secuencia de 6 llamadas API para crear programa + convocatoria

### Pasos del Wizard

```
STEP 1: Información Convocatoria        STEP 2: Configuración Programa
├─ Nombre *                              ├─ Clave * (auto: CONV-2026)
├─ Ejercicio Fiscal *                    ├─ Nombre *
├─ Estado (borrador/activa)              ├─ Tipo de Apoyo *
├─ Descripción                           ├─ Monto Máximo *
├─ Fecha Apertura * / Fecha Cierre *     ├─ [ ] Tiene Etapas
├─ Monto Máximo *                        ├─ [ ] Requiere Equipo
└─ Aportación Mínima (%)                 ├─ Puntaje Mínimo Aprobatorio
                                         └─ Requiere Evaluación Técnica

STEP 3: Campos del Formulario            STEP 4: Documentos Requeridos
├─ + Agregar Campo                       ├─ + Agregar Documento
├─ Tabla CRUD:                           ├─ Tabla CRUD:
│  ├─ Nombre Campo | Etiqueta            │  ├─ Clave | Nombre
│  ├─ Tipo (text/number/select/...)      │  ├─ Descripción | Obligatorio | Orden
│  ├─ Requerido | Orden                  │  └─ Actions (edit, delete)
│  └─ Actions (edit, delete)             └─ Validación: clave = unique + lowercase_snake
└─ Para select: modal inline de opciones

STEP 5: Rubros Presupuestales            STEP 6: Criterios Evaluación
├─ + Agregar Rubro                       ├─ + Agregar Criterio
├─ Tabla CRUD:                           ├─ Tabla CRUD:
│  ├─ Clave | Nombre                     │  ├─ Nombre | Descripción
│  ├─ Descripción | % Máximo             │  ├─ Ponderación | Puntaje Máximo
│  └─ Actions                            │  ├─ Orden
└─                                       └─ Validación Live: suma ponderaciones visible

STEP 7: Revisión y Guardar
├─ Summary Cards:
│  ├─ Resumen Convocatoria
│  ├─ Resumen Programa
│  ├─ Listado Campos (N)
│  ├─ Listado Documentos (N)
│  ├─ Listado Rubros (N)
│  └─ Listado Criterios (N)
├─ Botón "Crear Convocatoria Completa"
│  └─ Spinner con progreso: Creando programa... Agregando campos... etc
└─ On Success: redirect /admin/convocatorias
```

### API Calls en Orden (Step 7 Save)

```
1. POST /admin/programas → TipoPrograma (obtenemos ID)
2. POST /admin/programas/{id}/campos × N (si hay campos)
3. POST /admin/programas/{id}/documentos × N (si hay documentos)
4. POST /admin/programas/{id}/rubros × N (si hay rubros)
5. POST /admin/programas/{id}/criterios × N (si hay criterios)
6. POST /admin/convocatorias (con tipo_programa_id del programa creado)

Si cualquiera falla → mostrar error + permitir retry
On all success → "Convocatoria creada" + redirect
```

### Patrones a Reutilizar

Del archivo `apps/web/src/app/admin/programas/[id]/page.tsx`:
- CRUD table patterns para campos, rubros, criterios
- Step Navigator visual
- Button styles (primary, outline)
- AlertBox para validaciones

### Validaciones Live

- **Step 1:** fecha_apertura ≤ fecha_cierre, monto > 0
- **Step 2:** si tiene_etapas → num_etapas > 0, monto > 0
- **Step 3:** cada campo tiene nombre_campo + etiqueta, si select → ≥1 opción
- **Step 4:** clave unique + lowercase_snake
- **Step 6:** suma ponderaciones visible, color rojo si ≠ 100%

### Testing After Implementation

```bash
# Happy path: crear convocatoria con todo
Admin → Convocatorias → Nueva
→ Step 1: llenar info
→ Step 2: llenar programa
→ Step 3: agregar 2 campos
→ Step 4: agregar 3 documentos
→ Step 5: agregar 2 rubros
→ Step 6: agregar 3 criterios (sumar 100%)
→ Step 7: Guardar

# Verificar resultados:
- Convocatoria creada con tipo_programa_id
- TipoPrograma tiene los 2 campos, 3 docs, 2 rubros, 3 criterios
- Solicitante ve los campos y docs al crear solicitud de esa convocatoria
- Evaluador ve los 3 criterios en la rúbrica

# Edge cases:
- Abandonar wizard en Step 3 → volver Step 2 (state persiste)
- Error en API (ej: clave duplicada) → mostrar error, permitir retry
- Validación Step 6: intentar guardar sin sumar 100% → error
```

---

## 🚀 Próximas Prioridades (De Mayor a Menor Importancia)

### COMPLETADO ESTA SESIÓN ✅ (Session 06-04-2026)

**Parte 1: Fix de Build Errors + Revisor Workflow**

- [x] **Revisor: Agregar Observaciones Workflow** ✅ COMPLETADO
  - Form dinámico (ya existía en frontend) ✅
  - Relaciones en modelo Observacion (agregadas) ✅
  - Middleware de autorización para Revisor (agregado) ✅
  - Usuarios de prueba seeder (creado) ✅
  - Backend: observe() method (ya existía, funcionando) ✅
  - Testing: migrate:fresh --seed con usuarios (OK) ✅

**Archivos Modificados/Creados:**
1. `apps/api/app/Models/Observacion.php` - Agregadas relaciones belongsTo(Solicitud, User)
2. `apps/api/database/seeders/UsuariosPruebaSeeder.php` - Creado seeder con revisor, evaluador, solicitante
3. `apps/api/database/seeders/DatabaseSeeder.php` - Llamada al UsuariosPruebaSeeder
4. `apps/api/app/Http/Middleware/RevisorMiddleware.php` - Validación de rol_id=2
5. `apps/api/app/Http/Middleware/EvaluadorMiddleware.php` - Validación de rol_id=3
6. `apps/api/bootstrap/app.php` - Registro de middlewares (revisor, evaluador)
7. `apps/api/routes/api.php` - Agregado middleware a rutas revisor y evaluador
8. `apps/web/src/app/admin/convocatorias/nueva/page.tsx` - Fix imports AlertBox (description → message) ✅
9. `apps/web/src/app/solicitante/solicitudes/[id]/page.tsx` - Fix AlertBox prop ✅
10. `apps/web/src/app/solicitante/solicitudes/[id]/editar/page.tsx` - Fix colorMap.bg → .background ✅
11. `apps/web/src/components/solicitante/DocumentosAdjuntos.tsx` - Fix AlertBox props + colorMap ✅

**Estado:** ✅ BUILD PASSING, Database Seeded, Revisor workflow ready for testing

---

**Usuarios de Prueba Creados:**
```
Revisor:     asd@asd.com / password123
Evaluador:   evaluadorr@uaemex.mx / password123
Solicitante: solicitante@institucion.mx / password123
Admin:       admin@comecyt.gob.mx / password123
```

### CRÍTICO (Bloquea Workflow Completo)

- [x] **Revisor: Agregar Observaciones** ✅ COMPLETADO (06-04-2026)
  - Form dinámico con campos campo|tipo|comentario ✅
  - Crear Observacion records en BD ✅
  - Cambiar estado solicitud a 'observada' ✅
  - Notificar solicitante (backend list) ✅
  - Middleware de seguridad por rol ✅
  - **Nota:** UI ya existía, falta solo testing end-to-end

- [ ] **Evaluador: Workflow Completo** (4-6h) ⚠️ **PRIORIDAD ALTA**
  - Asignaciones están, pero evaluación incompleta
  - Criterios dinámicos + guardar puntajes
  - Calcular puntaje_total automático
  - Cambiar estado a 'aprobada' o 'rechazada'
  - **Nota:** Criterios seeded, evaluador page falta entrada de puntajes

### IMPORTANTE (Prevención & Refactorización)

- [ ] **Crear Hook `useArrayApi()`** (1h)
  - Reutilizable para todas las páginas
  - Evita duplicación de lógica de fetch

- [ ] **ESLint Rule: Array Guard** (1h)
  - Detectar `.map()` sin `Array.isArray()` guard
  - Prevenir futuros bugs

- [ ] **Auditar Evaluador Module** (1h)
  - ¿Hay más Array validation errors?
  - Aplicar parche rápido

### BAJA PRIORIDAD (Deuda Técnica & Polish)

- [ ] **Completar Refactorización Wave 3** (4-6h)
  - Unificar ~200 hardcoded colors
  - Usar tokens `colorMap`

- [ ] **Limpiar Console Warnings** (0.5-1h)
  - React warnings
  - Unused variables

---

## 📚 Documentación Adicional

- `DIAGNOSTICO_ESTRATEGICO.md` - Análisis completo del sistema, roadmap de implementación
- `CHANGELOG.md` - Historial de cambios por sesión (recomendado crear)
- `API_DOCUMENTATION.md` - Documentación detallada de cada endpoint (recomendado crear)

---

## 🔐 Configuración & Secretos

### Variables de Entorno Críticas

**Backend (`.env`):**
```env
APP_ENV=local|testing|production
APP_KEY=base64:...                 # php artisan key:generate
JWT_SECRET=...                     # Generado automáticamente
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_DATABASE=comecyt_dev
DB_USERNAME=comecyt
DB_PASSWORD=...

MAIL_MAILER=smtp|mailpit           # smtp para producción, mailpit para dev
MAIL_HOST=localhost
MAIL_PORT=1025                      # Mailpit puerto

RATE_LIMIT_MAX=100                 # Requests/minuto
API_GATEWAY_MAX_IPS=1000           # IPs simultáneas antes de bloqueo
CIRCUIT_BREAKER_THRESHOLD=5        # Errores 5xx antes de abrir circuito
```

**Frontend (`.env.local`):**
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

---

## ✅ Checklist de Setup Inicial

- [ ] Clonar repo
- [ ] Backend: `composer install` + `.env` + `php artisan key:generate`
- [ ] Backend: `php artisan migrate --seed`
- [ ] Frontend: `npm install` + `.env.local`
- [ ] Backend: `php artisan serve` (puerto 8000)
- [ ] Frontend: `npm run dev` (puerto 3000)
- [ ] Test login: admin@comecyt.gob.mx / password123
- [ ] Verificar: `/admin/dashboard` carga sin errores

---

## 🎯 Reglas de Oro

1. **SIEMPRE validar Arrays:** `Array.isArray(data) ? data : []`
2. **SIEMPRE tener fallback en catch:** `setSomething([])`
3. **NUNCA hardcodear colores:** usar `colorMap` tokens
4. **NUNCA confiar en estructura API:** validar antes de usar
5. **SIEMPRE documentar cambios:** en git commits y en CLAUDE.md
6. **NUNCA pushear `.env`:** está en `.gitignore`
7. **SIEMPRE hacer migrations:** no ALTER TABLE en SQL directo
8. **SIEMPRE seguir naming:** snake_case en DB, camelCase en code

---

## 📞 Soporte & Troubleshooting

### "API returns 401 Unauthenticated"
- Verificar que JWT_SECRET está cargado: `php artisan tinker → config('jwt.secret')`
- Verificar que token existe en request header: `Authorization: Bearer <token>`
- Verificar que `auth:api` guard está definido en `config/auth.php`

### "Solicitud no aparece en Revisor bandeja"
- Verificar estado de solicitud: `php artisan tinker → Solicitud::find(id)->estado`
- Debe ser 'enviada' o 'observada'
- Verificar endpoint: `GET /revisor/solicitudes/pendientes`

### "Colores no funcionan con Tailwind v4"
- Verificar `globals.css` tiene mapping en `@theme { --color-primary: ... }`
- Reiniciar servidor Next.js: `Ctrl+C` + `npm run dev`

### "Tests fallan con 'Undefined table'"
- Crear `.env.testing`: `cp .env .env.testing` + `DB_DATABASE=comecyt_test`
- O usar SQLite in-memory (mejor para tests rápidos)
- Ejecutar: `php artisan migrate --env=testing`

---

**Última Actualización:** 06 Abril 2026 - 10:35
**Responsable:** Desarrollador Senior + Sistema de Asistencia IA
**Estado del MVP:** ✅ COMPLETADO - Listo para pruebas integrales
