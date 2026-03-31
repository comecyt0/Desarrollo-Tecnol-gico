# Dynamic Program Catalogs API — Controller Design

## Overview

Design for a `ProgramaCatalogController` that serves dynamic form fields, documents, criteria, budgets, stages, and modalities for the 5 COMECYT programs (PFPI, PROT, IPFE, VINC, EMP).

Built on Laravel 11, with eager loading optimization and standardized JSON response structure.

---

## Decisions & Rationale

### 1. Response Inclusion & Filtering
- **Filter by `activo = true`** — Only return active records (business rule: inactive = archived)
- **Include nested relations** — Criteria grouped by stage, fields grouped by stage, documents grouped by stage
- **No pagination** — Catalogs are small reference data; return all matching records in single call
- **Nested format** — Clients get complete hierarchical data in one request (reduces N+1 queries)

### 2. Authentication & Access
- **Protect behind `auth:api` middleware** — Only authenticated users can view program catalogs (security: ensures audit trail)
- **No role-based filtering** — All authenticated users see the same catalog data (admin, revisor, evaluador, solicitante all need it)
- **Public alternative (future)** — Can add public endpoints without auth if needed for public-facing forms

### 3. Performance Optimizations
- **Eager loading all relations** — Use `with()` to prevent N+1 queries
- **Cache at controller level** — Wrap catalog queries with `Cache::remember()` (5-minute TTL)
- **Sort by `orden` field** — All collections ordered to ensure consistent frontend display
- **Select only needed columns** — Don't hydrate unused fields

### 4. Response Format
- **Wrapped responses** — Each endpoint returns data under a consistent key (e.g., `{ "data": [...] }`)
- **Include metadata** — Program info, active status, constraint info (ranges, limits)
- **Flat or nested based on use case** — Fields/docs/criteria include etapa context when multi-stage
- **NULL-safe JSON** — All optional fields present but nullable (no missing keys)

### 5. Validation Considerations
- **No input validation needed** — GET endpoints only; no user input to validate
- **ID must be valid** — Verify `tipo_programa_id` exists, return 404 if not
- **Only `tipo_programa_id` param** — Simpler route, easier to cache

---

## Controller Implementation

File: `/Users/fernandotorres/Desktop/comecyt-system/apps/api/app/Http/Controllers/Catalogos/ProgramaCatalogController.php`

```php
<?php

namespace App\Http\Controllers\Catalogos;

use App\Http\Controllers\Controller;
use App\Models\TipoPrograma;
use App\Models\ProgramaCampo;
use App\Models\ProgramaDocumento;
use App\Models\ProgramaCriterioEvaluacion;
use App\Models\ProgramaRubro;
use App\Models\ProgramaEtapa;
use App\Models\ProgramaModalidad;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProgramaCatalogController extends Controller
{
    // Cache TTL in minutes
    private const CACHE_TTL = 5;
    private const CACHE_PREFIX = 'programa_catalog_';

    /**
     * Get form fields for a program (by stage if multi-stage)
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/campos
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, tiene_etapas, num_etapas, ... },
     *     "campos": [
     *       {
     *         "id": 1,
     *         "nombre_campo": "titulo_proyecto",
     *         "etiqueta": "Título del Proyecto",
     *         "tipo_campo": "text",
     *         "orden": 1,
     *         "requerido": true,
     *         "etapa_id": null,
     *         "etapa": null,
     *         "opciones_json": null,
     *         "reglas_validacion_json": { "maxLength": 500, "pattern": "..." }
     *       },
     *       {
     *         "id": 2,
     *         "nombre_campo": "monto_solicitado",
     *         "etiqueta": "Monto Solicitado",
     *         "tipo_campo": "number",
     *         "orden": 2,
     *         "requerido": true,
     *         "etapa_id": 1,
     *         "etapa": { "id": 1, "numero_etapa": 1, "nombre": "Etapa 1", ... },
     *         "opciones_json": null,
     *         "reglas_validacion_json": { "min": 0, "max": 350000 }
     *       }
     *     ],
     *     "etapas": [
     *       { "id": 1, "numero_etapa": 1, "nombre": "Etapa 1", ... }
     *     ]
     *   },
     *   "message": "Campos del programa obtenidos exitosamente"
     * }
     */
    public function campos(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'campos_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $campos = ProgramaCampo::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->with('etapa')
                ->orderBy('orden')
                ->get();

            $etapas = $programa->tiene_etapas
                ? ProgramaEtapa::where('tipo_programa_id', $tipo_programa_id)
                    ->where('activo', true)
                    ->orderBy('numero_etapa')
                    ->get()
                : [];

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre', 'descripcion',
                    'tiene_etapas', 'num_etapas', 'monto_maximo',
                    'porcentaje_aportacion_solicitante', 'activo'
                ]),
                'campos' => $campos,
                'etapas' => $etapas
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Campos del programa obtenidos exitosamente'
        ]);
    }

    /**
     * Get required documents for a program (by stage if multi-stage)
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/documentos
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, ... },
     *     "documentos": [
     *       {
     *         "id": 1,
     *         "nombre": "RFC",
     *         "descripcion": "Registro Federal de Contribuyentes",
     *         "formato_permitido": "PDF",
     *         "tamaño_maximo_mb": 5,
     *         "obligatorio": true,
     *         "orden": 1,
     *         "etapa_id": null,
     *         "etapa": null
     *       },
     *       {
     *         "id": 2,
     *         "nombre": "Propuesta Técnica",
     *         "descripcion": "Documento detallado del proyecto",
     *         "formato_permitido": "PDF,DOCX",
     *         "tamaño_maximo_mb": 10,
     *         "obligatorio": true,
     *         "orden": 2,
     *         "etapa_id": 1,
     *         "etapa": { "id": 1, "numero_etapa": 1, "nombre": "Etapa 1" }
     *       }
     *     ],
     *     "etapas": [...]
     *   },
     *   "message": "Documentos requeridos obtenidos exitosamente"
     * }
     */
    public function documentos(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'documentos_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $documentos = ProgramaDocumento::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->with('etapa')
                ->orderBy('orden')
                ->get();

            $etapas = $programa->tiene_etapas
                ? ProgramaEtapa::where('tipo_programa_id', $tipo_programa_id)
                    ->where('activo', true)
                    ->orderBy('numero_etapa')
                    ->get()
                : [];

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre',
                    'tiene_etapas', 'num_etapas', 'activo'
                ]),
                'documentos' => $documentos,
                'etapas' => $etapas
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Documentos requeridos obtenidos exitosamente'
        ]);
    }

    /**
     * Get evaluation criteria for a program (grouped by stage)
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/criterios
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, requiere_evaluacion_tecnica, puntaje_minimo_aprobatorio, ... },
     *     "criterios": [
     *       {
     *         "id": 1,
     *         "nombre": "Viabilidad Técnica",
     *         "descripcion": "Evaluación de la factibilidad técnica",
     *         "ponderacion": 25.00,
     *         "puntaje_maximo": 100,
     *         "orden": 1,
     *         "etapa_id": null,
     *         "etapa": null
     *       }
     *     ],
     *     "etapas": [
     *       {
     *         "id": 1,
     *         "numero_etapa": 1,
     *         "nombre": "Evaluación Inicial",
     *         "es_evaluacion_tecnica": true,
     *         "criterios_en_etapa": [
     *           { "id": 1, "nombre": "...", "ponderacion": 25.00, ... }
     *         ]
     *       }
     *     ],
     *     "suma_ponderaciones": 100.00,
     *     "num_criterios": 4
     *   },
     *   "message": "Criterios de evaluación obtenidos exitosamente"
     * }
     */
    public function criterios(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'criterios_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $criterios = ProgramaCriterioEvaluacion::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->with('etapa')
                ->orderBy('orden')
                ->get();

            $etapas = $programa->tiene_etapas
                ? ProgramaEtapa::where('tipo_programa_id', $tipo_programa_id)
                    ->where('activo', true)
                    ->with('criterios')
                    ->orderBy('numero_etapa')
                    ->get()
                    ->map(function ($etapa) {
                        $etapa['criterios_en_etapa'] = $etapa->criterios()
                            ->where('activo', true)
                            ->orderBy('orden')
                            ->get()
                            ->makeHidden(['tipo_programa_id', 'etapa_id', 'created_at', 'updated_at'])
                            ->toArray();
                        return $etapa;
                    })
                : [];

            $sumaPonderaciones = $criterios->sum('ponderacion');

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre',
                    'requiere_evaluacion_tecnica', 'puntaje_minimo_aprobatorio',
                    'tiene_etapas', 'activo'
                ]),
                'criterios' => $criterios,
                'etapas' => $etapas,
                'suma_ponderaciones' => $sumaPonderaciones,
                'num_criterios' => $criterios->count()
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Criterios de evaluación obtenidos exitosamente'
        ]);
    }

    /**
     * Get budget items (rubros) for a program
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/rubros
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, monto_maximo, ... },
     *     "rubros": [
     *       {
     *         "id": 1,
     *         "clave": "VIAJES",
     *         "nombre": "Viajes y Movilidad",
     *         "descripcion": "Gastos de transporte y hospedaje",
     *         "porcentaje_maximo": 30.00,
     *         "orden": 1
     *       },
     *       {
     *         "id": 2,
     *         "clave": "MATERIALES",
     *         "nombre": "Materiales y Consumibles",
     *         "descripcion": "Materiales necesarios para el proyecto",
     *         "porcentaje_maximo": 40.00,
     *         "orden": 2
     *       }
     *     ]
     *   },
     *   "message": "Rubros presupuestarios obtenidos exitosamente"
     * }
     */
    public function rubros(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'rubros_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $rubros = ProgramaRubro::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->orderBy('nombre')
                ->select('id', 'clave', 'nombre', 'descripcion', 'porcentaje_maximo')
                ->get();

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre', 'monto_maximo',
                    'tipo_apoyo', 'activo'
                ]),
                'rubros' => $rubros
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Rubros presupuestarios obtenidos exitosamente'
        ]);
    }

    /**
     * Get stages/phases for a program
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/etapas
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, tiene_etapas, num_etapas, ... },
     *     "etapas": [
     *       {
     *         "id": 1,
     *         "numero_etapa": 1,
     *         "nombre": "Formulación y Presentación",
     *         "descripcion": "Etapa de presentación de propuestas",
     *         "duracion_meses": 6,
     *         "es_evaluacion_tecnica": false,
     *         "puntaje_minimo": null,
     *         "orden": 1
     *       },
     *       {
     *         "id": 2,
     *         "numero_etapa": 2,
     *         "nombre": "Evaluación Técnica",
     *         "descripcion": "Revisión técnica de propuestas",
     *         "duracion_meses": 2,
     *         "es_evaluacion_tecnica": true,
     *         "puntaje_minimo": 80.00,
     *         "orden": 2
     *       }
     *     ]
     *   },
     *   "message": "Etapas del programa obtenidas exitosamente"
     * }
     */
    public function etapas(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'etapas_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $etapas = ProgramaEtapa::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->orderBy('numero_etapa')
                ->select(
                    'id', 'numero_etapa', 'nombre', 'descripcion',
                    'duracion_meses', 'es_evaluacion_tecnica', 'puntaje_minimo'
                )
                ->get();

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre',
                    'tiene_etapas', 'num_etapas', 'activo'
                ]),
                'etapas' => $etapas,
                'tiene_etapas' => $programa->tiene_etapas,
                'num_etapas' => $programa->num_etapas
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Etapas del programa obtenidas exitosamente'
        ]);
    }

    /**
     * Get modalities/variants for a program
     *
     * GET /api/catalogs/programa/{tipo_programa_id}/modalidades
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     *
     * Response:
     * {
     *   "data": {
     *     "programa": { id, clave, nombre, ... },
     *     "modalidades": [
     *       {
     *         "id": 1,
     *         "clave": "PRESENCIAL",
     *         "nombre": "Evento Presencial",
     *         "descripcion": "Evento realizado de forma presencial",
     *         "monto_maximo_especifico": 60000.00
     *       },
     *       {
     *         "id": 2,
     *         "clave": "VIRTUAL",
     *         "nombre": "Evento Virtual",
     *         "descripcion": "Evento realizado en línea",
     *         "monto_maximo_especifico": 30000.00
     *       },
     *       {
     *         "id": 3,
     *         "clave": "HIBRIDO",
     *         "nombre": "Evento Híbrido",
     *         "descripcion": "Evento con participación presencial y virtual",
     *         "monto_maximo_especifico": 45000.00
     *       }
     *     ]
     *   },
     *   "message": "Modalidades del programa obtenidas exitosamente"
     * }
     */
    public function modalidades(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'modalidades_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::findOrFail($tipo_programa_id);

            $modalidades = ProgramaModalidad::where('tipo_programa_id', $tipo_programa_id)
                ->where('activo', true)
                ->orderBy('nombre')
                ->select('id', 'clave', 'nombre', 'descripcion', 'monto_maximo_especifico')
                ->get();

            return [
                'programa' => $programa->only([
                    'id', 'clave', 'nombre', 'monto_maximo', 'activo'
                ]),
                'modalidades' => $modalidades
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Modalidades del programa obtenidas exitosamente'
        ]);
    }

    /**
     * Get complete program configuration (one call for all catalog data)
     *
     * GET /api/catalogs/programa/{tipo_programa_id}
     *
     * Useful for initial form load to get everything at once.
     * Returns campos, documentos, criterios, rubros, etapas, modalidades.
     *
     * @param int $tipo_programa_id
     * @return JsonResponse
     */
    public function show(int $tipo_programa_id): JsonResponse
    {
        $cacheKey = self::CACHE_PREFIX . 'completo_' . $tipo_programa_id;

        $response = Cache::remember($cacheKey, self::CACHE_TTL * 60, function () use ($tipo_programa_id) {
            $programa = TipoPrograma::with([
                'campos' => function ($query) {
                    $query->where('activo', true)->orderBy('orden');
                },
                'documentos' => function ($query) {
                    $query->where('activo', true)->orderBy('orden');
                },
                'criterios' => function ($query) {
                    $query->where('activo', true)->orderBy('orden');
                },
                'rubros' => function ($query) {
                    $query->where('activo', true)->orderBy('nombre');
                },
                'etapas' => function ($query) {
                    $query->where('activo', true)->orderBy('numero_etapa');
                },
                'modalidades' => function ($query) {
                    $query->where('activo', true)->orderBy('nombre');
                }
            ])->findOrFail($tipo_programa_id);

            return [
                'programa' => $programa,
                'campos' => $programa->campos,
                'documentos' => $programa->documentos,
                'criterios' => $programa->criterios,
                'rubros' => $programa->rubros,
                'etapas' => $programa->etapas,
                'modalidades' => $programa->modalidades
            ];
        });

        return response()->json([
            'data' => $response,
            'message' => 'Configuración completa del programa obtenida'
        ]);
    }
}
```

---

## Routes Configuration

Add to `routes/api.php` (already has protection from `auth:api` middleware):

```php
// PROGRAM CATALOGS (Todos los autenticados)
Route::group(['prefix' => 'catalogs/programa'], function () {
    // Individual endpoints (fetch specific catalog type)
    Route::get('{tipo_programa_id}/campos', [ProgramaCatalogController::class, 'campos']);
    Route::get('{tipo_programa_id}/documentos', [ProgramaCatalogController::class, 'documentos']);
    Route::get('{tipo_programa_id}/criterios', [ProgramaCatalogController::class, 'criterios']);
    Route::get('{tipo_programa_id}/rubros', [ProgramaCatalogController::class, 'rubros']);
    Route::get('{tipo_programa_id}/etapas', [ProgramaCatalogController::class, 'etapas']);
    Route::get('{tipo_programa_id}/modalidades', [ProgramaCatalogController::class, 'modalidades']);

    // Complete configuration (one call for all)
    Route::get('{tipo_programa_id}', [ProgramaCatalogController::class, 'show']);
});
```

---

## Error Handling

All endpoints inherit Laravel's default exception handling:

- **404** — If `tipo_programa_id` doesn't exist → `TipoPrograma::findOrFail()` throws `ModelNotFoundException`
- **401** — If user not authenticated → `auth:api` middleware rejects
- **500** — If database query fails → caught by global exception handler

Add this to `app/Exceptions/Handler.php` if you want custom 404 response:

```php
public function render($request, Throwable $exception)
{
    if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
        if ($request->wantsJson()) {
            return response()->json([
                'error' => 'Programa no encontrado',
                'message' => 'El tipo de programa solicitado no existe'
            ], 404);
        }
    }

    return parent::render($request, $exception);
}
```

---

## Cache Invalidation

When programa catalogs are updated (admin creates/edits fields, documents, etc.), invalidate cache:

```php
// In your admin update controllers:
use Illuminate\Support\Facades\Cache;

public function update($id, Request $request)
{
    $programa = TipoPrograma::findOrFail($id);
    $programa->update($request->validated());

    // Invalidate all caches for this programa
    Cache::forget('programa_catalog_campos_' . $id);
    Cache::forget('programa_catalog_documentos_' . $id);
    Cache::forget('programa_catalog_criterios_' . $id);
    Cache::forget('programa_catalog_rubros_' . $id);
    Cache::forget('programa_catalog_etapas_' . $id);
    Cache::forget('programa_catalog_modalidades_' . $id);
    Cache::forget('programa_catalog_completo_' . $id);

    return response()->json([
        'message' => 'Programa actualizado',
        'data' => $programa
    ]);
}
```

Or use a helper method:

```php
// In a helper or trait
public static function invalidateProgramaCatalogCache(int $tipoProgramaId)
{
    $cacheKey = 'programa_catalog_';
    Cache::forget($cacheKey . 'campos_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'documentos_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'criterios_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'rubros_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'etapas_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'modalidades_' . $tipoProgramaId);
    Cache::forget($cacheKey . 'completo_' . $tipoProgramaId);
}
```

---

## Frontend Usage Examples

### React (Next.js)

Fetch all catalogs for a program:

```typescript
// lib/api.ts
export async function getProgramaCatalog(tipoProgramaId: number) {
    const response = await api.get(`/catalogs/programa/${tipoProgramaId}`);
    return response.data.data;
}

export async function getProgramaCampos(tipoProgramaId: number) {
    const response = await api.get(`/catalogs/programa/${tipoProgramaId}/campos`);
    return response.data.data;
}
```

Use in form component:

```typescript
// components/SolicitudForm.tsx
const [programa, setProgramma] = useState<any>(null);
const [campos, setCampos] = useState<any[]>([]);

useEffect(() => {
    const loadCatalog = async () => {
        try {
            const data = await getProgramaCatalog(selectedProgramaId);
            setProgramma(data.programa);
            setCampos(data.campos);
        } catch (error) {
            console.error('Error loading catalog:', error);
        }
    };

    loadCatalog();
}, [selectedProgramaId]);

return (
    <form>
        {campos.map((campo) => (
            <FormField key={campo.id} field={campo} />
        ))}
    </form>
);
```

---

## Testing

Sample test file: `tests/Feature/ProgramaCatalogControllerTest.php`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TipoPrograma;
use App\Models\ProgramaCampo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaCatalogControllerTest extends RefreshDatabase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_get_campos_returns_campos_grouped_by_etapa()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(3))
            ->create(['tiene_etapas' => false]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['programa', 'campos', 'etapas'],
                'message'
            ])
            ->assertJsonCount(3, 'data.campos');
    }

    public function test_get_criterios_sums_ponderaciones()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCriterioEvaluacion::factory()->count(4))
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertStatus(200)
            ->assertJsonPath('data.suma_ponderaciones', 100.0);
    }

    public function test_returns_404_for_invalid_programa()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/catalogs/programa/9999/campos');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(401);
    }
}
```

---

## Performance Notes

### Query Counts

| Endpoint | Queries | Cache Hit |
|----------|---------|-----------|
| `/campos` | 2 (programa + campos) | 1 |
| `/documentos` | 2 (programa + documentos) | 1 |
| `/criterios` | 3 (programa + criterios + etapas) | 1 |
| `/rubros` | 2 (programa + rubros) | 1 |
| `/etapas` | 2 (programa + etapas) | 1 |
| `/modalidades` | 2 (programa + modalidades) | 1 |
| `/show` (complete) | 7 (eager loads all) | 1 |

### Optimization Tips

1. **Use `select()` to minimize hydration** — Only select needed columns
2. **Cache aggressively** — 5 minutes is reasonable for reference data
3. **Pre-load relationships** — Use `with()` to prevent N+1
4. **Order by natural sort fields** — `orden` for UI consistency
5. **Filter early** — Apply `where('activo', true)` at query time

---

## Summary

| Aspect | Decision |
|--------|----------|
| **Auth** | `auth:api` middleware (all roles) |
| **Caching** | 5-minute TTL per endpoint |
| **Pagination** | None (small reference data) |
| **Format** | Wrapped `{ data: {...}, message: "..." }` |
| **Nesting** | Criteria/docs/fields include etapa context |
| **Filtering** | Only `activo = true` records |
| **Error codes** | 404 for missing programa, 401 for unauth |
| **Eager loading** | All relations loaded upfront |

---

## Migration Notes

If running fresh, catalogs can be seeded with:

```php
// database/seeders/ProgramaCatalogSeeder.php
use App\Models\TipoPrograma;

class ProgramaCatalogSeeder extends Seeder
{
    public function run()
    {
        // Create 5 programas: PFPI, PROT, IPFE, VINC, EMP
        $programas = [
            ['clave' => 'PFPI', 'nombre' => 'Programa de Formación de Personal de Investigación'],
            ['clave' => 'PROT', 'nombre' => 'Programa de Investigación Tecnológica'],
            ['clave' => 'IPFE', 'nombre' => 'Impulso a Proyectos de Fortalecimiento Estatal'],
            ['clave' => 'VINC', 'nombre' => 'Vinculación Universidad-Empresa'],
            ['clave' => 'EMP', 'nombre' => 'Emprendimiento Científico']
        ];

        foreach ($programas as $data) {
            TipoPrograma::create($data + [
                'tipo_apoyo' => 'reembolso',
                'monto_maximo' => 60000,
                'activo' => true
            ]);
        }
    }
}
```

Then run: `php artisan db:seed --class=ProgramaCatalogSeeder`
