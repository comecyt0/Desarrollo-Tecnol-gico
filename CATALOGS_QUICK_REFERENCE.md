# Program Catalogs API — Quick Reference Card

## Endpoints (All require `Authorization: Bearer <token>`)

```
GET /api/catalogs/programa/{id}                 # All data (complete config)
GET /api/catalogs/programa/{id}/campos          # Form fields
GET /api/catalogs/programa/{id}/documentos      # File uploads
GET /api/catalogs/programa/{id}/criterios       # Evaluation rubric
GET /api/catalogs/programa/{id}/rubros          # Budget items
GET /api/catalogs/programa/{id}/etapas          # Stages/phases
GET /api/catalogs/programa/{id}/modalidades     # Variants
```

---

## Response Format

All responses follow this structure:
```json
{
  "data": {
    "programa": { /* TipoPrograma object */ },
    "campos": [ /* array */ ],
    "documentos": [ /* array */ ],
    /* ... other arrays ... */
  },
  "message": "Success message"
}
```

---

## Frontend Usage (React)

### Import
```typescript
import { getProgramaCatalog } from '@/lib/api';
```

### Load
```typescript
const data = await getProgramaCatalog(tipoProgramaId);
// data.programa, data.campos, data.documentos, data.criterios, ...
```

### Render
```typescript
<FormField key={campo.id} field={campo} value={...} onChange={...} />
```

---

## Field Types

| tipo_campo | Input | Example |
|-----------|-------|---------|
| `text` | `<Input type="text">` | Título, nombre |
| `number` | `<Input type="number">` | Monto, cantidad |
| `date` | `<Input type="date">` | Fecha inicio, fecha fin |
| `textarea` | `<Textarea>` | Descripción, resumen |
| `select` | `<Select>` | Dropdown (opciones_json) |
| `file` | `<Input type="file">` | Document upload |

---

## Key Properties

### Field (ProgramaCampo)
- `nombre_campo` — form key
- `etiqueta` — display label
- `requerido` — validation flag
- `tipo_campo` — input type
- `opciones_json` — select options `[{id, label}]`
- `reglas_validacion_json` — constraints `{min, max, maxLength, ...}`
- `etapa_id` — stage (null = all stages)

### Document (ProgramaDocumento)
- `nombre` — "RFC", "Propuesta Técnica"
- `formato_permitido` — "PDF", "DOCX", "PDF,DOCX"
- `tamaño_maximo_mb` — file size limit
- `obligatorio` — required flag
- `etapa_id` — stage (null = all stages)

### Criterion (ProgramaCriterioEvaluacion)
- `nombre` — "Viabilidad Técnica"
- `ponderacion` — weight (25.00 = 25%)
- `puntaje_maximo` — 100
- `etapa_id` — stage (null = all stages)

### Stage (ProgramaEtapa)
- `numero_etapa` — 1, 2, 3...
- `nombre` — "Formulación", "Evaluación"
- `duracion_meses` — timeline
- `es_evaluacion_tecnica` — boolean
- `puntaje_minimo` — passing score

### Rublo (ProgramaRubro)
- `clave` — "VIAJES", "MATERIALES"
- `nombre` — display name
- `porcentaje_maximo` — 30.00 (budget %cap)

### Modality (ProgramaModalidad)
- `clave` — "PRESENCIAL", "VIRTUAL"
- `nombre` — display name
- `monto_maximo_especifico` — variant budget cap

---

## Validation Rules (reglas_validacion_json)

```json
{
  "min": 0,                    // Number minimum
  "max": 350000,               // Number maximum
  "maxLength": 500,            // Text max chars
  "pattern": "^[A-Z]{3}$",     // Regex pattern
  "rows": 4,                   // Textarea rows
  "step": 100                  // Number increment
}
```

---

## Cache Keys (Backend)

```php
'programa_catalog_campos_1'          // Field cache for program 1
'programa_catalog_documentos_1'      // Document cache
'programa_catalog_criterios_1'       // Criteria cache
'programa_catalog_rubros_1'          // Budget cache
'programa_catalog_etapas_1'          // Stage cache
'programa_catalog_modalidades_1'     // Modality cache
'programa_catalog_completo_1'        // Complete config cache

// TTL: 5 minutes (300 seconds)
```

**Invalidate:**
```php
use App\Helpers\ProgramaCatalogHelper;

ProgramaCatalogHelper::invalidateForProgram($programa->id);
```

---

## Common Patterns

### Group Fields by Stage
```typescript
const groupedByStage = campos.reduce((acc, campo) => {
  const stageId = campo.etapa_id || 'sin_etapa';
  if (!acc[stageId]) acc[stageId] = [];
  acc[stageId].push(campo);
  return acc;
}, {});

// Render
etapas.map(etapa => (
  <div key={etapa.id}>
    <h3>{etapa.nombre}</h3>
    {groupedByStage[etapa.id]?.map(campo => <FormField {...} />)}
  </div>
))
```

### Validate Required Fields
```typescript
const errors = {};
campos.forEach(campo => {
  if (campo.requerido && !formData[campo.nombre_campo]) {
    errors[campo.nombre_campo] = `${campo.etiqueta} required`;
  }
});
if (Object.keys(errors).length > 0) return errors;
```

### Apply Number Rules
```typescript
const rules = campo.reglas_validacion_json || {};
<Input
  type="number"
  min={rules.min}
  max={rules.max}
  step={rules.step || 1}
/>
```

### Check File Constraints
```typescript
const doc = documentos[0];
const isValid = file &&
  file.size <= doc.tamaño_maximo_mb * 1024 * 1024 &&
  doc.formato_permitido.includes(file.type);
```

---

## Error Codes

| Code | Meaning | Action |
|------|---------|--------|
| `200` | ✅ Success | Proceed with data |
| `401` | 🔐 No auth | Get JWT token first |
| `404` | ❌ Not found | Verify program ID exists |
| `429` | 🚫 Rate limited | Retry after Retry-After header |
| `500` | 💥 Server error | Check backend logs |

---

## Testing Endpoints

```bash
# Auth (get token first)
TOKEN=$(curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@comecyt.mx","password":"password"}' \
  | jq -r '.access_token')

# Test endpoint
curl -X GET http://localhost:8000/api/catalogs/programa/1/campos \
  -H "Authorization: Bearer $TOKEN" | jq

# Pretty print
... | jq '.data.campos[0]'
```

---

## Files to Know

### Backend
- `app/Http/Controllers/Catalogos/ProgramaCatalogController.php` — Main logic
- `app/Helpers/ProgramaCatalogHelper.php` — Cache invalidation
- `routes/api.php` — Route definitions (lines 94-106)

### Frontend
- `lib/api.ts` — API client & catalog functions
- `components/FormField.tsx` — Dynamic field renderer
- `components/SolicitudForm.tsx` — Complete form
- `components/DocumentUpload.tsx` — File upload manager
- `components/CriteriosList.tsx` — Evaluation display
- `types/programa.ts` — TypeScript definitions

### Tests
- `tests/Feature/ProgramaCatalogControllerTest.php` — 26 test cases

### Documentation
- `API_CATALOGS_DESIGN.md` — Full architectural design
- `PROGRAM_CATALOGS_FRONTEND_GUIDE.md` — React integration (detailed)
- `IMPLEMENTATION_SUMMARY.md` — Project overview
- `CATALOGS_QUICK_REFERENCE.md` — This file

---

## Program IDs (Reference)

| ID | Clave | Nombre |
|----|-------|--------|
| 1 | PFPI | Programa de Formación de Personal de Investigación |
| 2 | PROT | Programa de Investigación Tecnológica |
| 3 | IPFE | Impulso a Proyectos de Fortalecimiento Estatal |
| 4 | VINC | Vinculación Universidad-Empresa |
| 5 | EMP | Emprendimiento Científico |

---

## Pro Tips

1. **Cache aggressively** — Use `getProgramaCatalog()` once per page load, not per field
2. **Validate on frontend** — Apply `reglas_validacion_json` before backend submission
3. **Show required indicator** — Render `*` for fields with `requerido = true`
4. **Group by etapa** — If `tiene_etapas = true`, organize form by `etapa.numero_etapa`
5. **Handle file validation** — Check `formato_permitido` and `tamaño_maximo_mb` client-side
6. **Show ponderación** — Display criteria weights as percentages (e.g., "25% de la nota")
7. **Test with cache miss** — Manually clear cache to test first-load performance
8. **Monitor 404s** — Alert if program doesn't exist (bad ID passed)

---

## Status & Support

✅ **Status:** Production-ready

📚 **Documentation:** See `API_CATALOGS_DESIGN.md` for complete specs

🧪 **Tests:** Run `php artisan test tests/Feature/ProgramaCatalogControllerTest.php`

💬 **Questions:** Check `IMPLEMENTATION_SUMMARY.md` troubleshooting section
