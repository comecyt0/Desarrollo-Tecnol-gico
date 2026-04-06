# 🧪 COMECYT MVP Integration Testing Plan

**Status:** MVP Complete - Ready for End-to-End Testing
**Date:** April 6, 2026
**Version:** 1.0

---

## 📋 Quick Start

### Prerequisites
```bash
# Terminal 1: Backend
cd apps/api
php artisan serve  # http://localhost:8000

# Terminal 2: Frontend
cd apps/web
npm run dev  # http://localhost:3000

# Terminal 3 (optional): Monitor
cd apps/api
php artisan tinker  # For quick DB queries
```

### Test Users
| Role | Email | Password | Use For |
|------|-------|----------|---------|
| Admin | admin@comecyt.gob.mx | password123 | Admin flows |
| Revisor | asd@asd.com | password123 | Review/observation flows |
| Evaluador | evaluadorr@uaemex.mx | password123 | Evaluation flows |
| Solicitante | solicitante@institucion.mx | password123 | Request submission |

---

## ✅ Test Scenarios (MVP Completion)

### 1. ADMIN WORKFLOW ✅ COMPLETE
**Scope:** Convocatoria creation with 7-step wizard

#### Scenario: Create Complete Convocatoria
```
Admin Dashboard → Convocatorias → Nueva
├─ Step 1: Información Convocatoria
│  ├─ Llenar: Nombre, Ejercicio Fiscal, Descripción
│  ├─ Fechas: Apertura ≤ Cierre
│  ├─ Montos: Máximo > 0, Aportación Mínima 0-100%
│  └─ Validación live: Errores en rojo si inválido
│
├─ Step 2: Configuración Programa
│  ├─ Clave auto-sugerida (CONV-2026, editable)
│  ├─ Nombre, Tipo Apoyo, Monto Máximo
│  ├─ Checkbox: Tiene Etapas → num_etapas input
│  ├─ Checkbox: Requiere Equipo → min/max inputs
│  └─ Puntaje Mínimo Aprobatorio (default 80)
│
├─ Step 3: Campos Dinámicos
│  ├─ + Agregar Campo button
│  ├─ Tabla CRUD: Nombre, Etiqueta, Tipo, Requerido, Orden
│  ├─ Tipos: text, number, select, date, textarea
│  ├─ Si type=select → modal para agregar opciones
│  └─ Validación: ≥1 campo required
│
├─ Step 4: Documentos Requeridos
│  ├─ + Agregar Documento button
│  ├─ Tabla CRUD: Clave (unique), Nombre, Descripción, Obligatorio, Orden
│  ├─ Clave format: lowercase_snake (validación live)
│  └─ Validación: ≥1 documento required
│
├─ Step 5: Rubros Presupuestales
│  ├─ + Agregar Rubro button
│  ├─ Tabla CRUD: Clave, Nombre, Descripción, % Máximo
│  └─ Validación: ≥1 rubro required
│
├─ Step 6: Criterios Evaluación
│  ├─ + Agregar Criterio button
│  ├─ Tabla CRUD: Nombre, Descripción, Ponderación, Puntaje Máximo
│  ├─ Live validation: "Ponderación Total: X%" + color change if ≠ 100%
│  └─ Validación: ≥1 criterio + suma = 100%
│
├─ Step 7: Revisión y Guardar
│  ├─ Summary cards showing all data
│  ├─ Botón "Crear Convocatoria Completa"
│  ├─ Spinner with progress: "Creando programa..." → "Agregando campos..."
│  └─ On success: Redirect /admin/convocatorias with success toast
│
└─ Expected: TipoPrograma + Convocatoria created with all nested data
```

**Verification:**
- [ ] All 7 steps render without errors
- [ ] Step validator prevents advancing with incomplete data
- [ ] Back/Next buttons work correctly
- [ ] Progress bar shows correct status
- [ ] Modals for nested CRUD (select options, etc) open/close
- [ ] Step 7 spinner shows progress messages
- [ ] Success redirect to /admin/convocatorias shows new convocatoria
- [ ] Database: Convocatoria.tipo_programa_id populated, TipoPrograma has all nested records

---

### 2. SOLICITANTE WORKFLOW ✅ COMPLETE
**Scope:** Create solicitud → Upload docs → Submit for review

#### Scenario A: Create Solicitud and Upload Documents
```
Solicitante Dashboard → Solicitudes → Nueva
├─ 1. Seleccionar Convocatoria (dropdown con las reales)
├─ 2. Llenar formulario dinámico (campos del programa)
│  ├─ Campos renderizados según programa seleccionado
│  ├─ Validación de tipos: text, number, date, select, textarea
│  └─ Requeridos marcados con *
├─ 3. Sección Miembros Equipo (si programa.tiene_equipo)
│  ├─ Validación min/max members
│  ├─ + Agregar Miembro button
│  └─ Tabla con delete option
├─ 4. Sección Rubros Presupuestales (si existen)
│  ├─ Tabla con montos editable
│  ├─ Validación: suma ≤ monto_máximo
│  └─ Color feedback: green si válido, red si excede
├─ 5. Botón "Crear e Ir a Documentos" (no "Someter a Revisión")
│  └─ Crea solicitud en estado 'borrador'
│     Redirige a /solicitudes/{id}
└─ Success: "Solicitud creada. Ahora debes subir documentos."

Solicitud Detail Page → Upload Documentos
├─ Sección "Documentos Adjuntos"
├─ Tabla dinámicamente renderizada (según programa.documentos)
├─ Para cada documento:
│  ├─ Nombre documento (del programa)
│  ├─ File input (aceptar solo PDF)
│  ├─ Botón "Subir PDF" → abre file picker
│  ├─ Botón "Ver Previo" → abre modal con PDF preview (iframe)
│  ├─ Botón "Descargar" → descarga PDF
│  ├─ Botón "Eliminar" → delete with confirmation
│  └─ Indicador si obligatorio (red star)
├─ Validación: mín size, máx 5MB, solo PDF
└─ On upload success: Archivo persiste en storage/app/public

Enviar Solicitud
├─ Botón "Enviar para Revisión" en documento detail page
├─ Validación frontend:
│  ├─ Cargar tipos de documentos requeridos
│  ├─ Validar documentos obligatorios están presentes
│  ├─ Si faltantes → AlertBox rojo con lista: "Faltan: Ficha Técnica, ..."
│  └─ Bloquear submit si faltan
├─ Si pasa validación → API call POST /solicitudes/{id}/enviar
├─ Backend validación redundante:
│  └─ Si frontend se saltó validación → 422 + detalles faltantes
└─ Success: Estado → 'enviada', success toast, redirect bandeja
```

**Verification:**
- [ ] New solicitud draft created in 'borrador' state
- [ ] Formulario dinámico renderiza campos del programa correcto
- [ ] Miembros equipo: agregar, validar min/max, eliminar funcionan
- [ ] Rubros presupuestales: mostrar, editar, validar suma funcionan
- [ ] Documentos adjuntos: tabla muestra docs requeridos del programa
- [ ] File upload: PDF accepted, mín/máx size validated, storage successful
- [ ] File preview: iframe abre modal, cierra con X button
- [ ] File download: descarga PDF correctamente
- [ ] File delete: elimina con confirmación
- [ ] Enviar: validación frontend bloquea si docs faltantes
- [ ] Enviar: API call exitoso → estado → 'enviada'
- [ ] Database: Solicitud + SolicitudDocumento records creados

---

### 3. REVISOR WORKFLOW ✅ COMPLETE
**Scope:** Review solicitudes → Add observaciones → Approve/Reject

#### Scenario: Review and Observe Solicitud
```
Revisor Dashboard
├─ Estadísticas:
│  ├─ "Nuevos" card: count(estado='enviada')
│  ├─ "En Subsanación" card: count(estado='observada')
│  └─ "Urgentes" card: count(updated_at < 7 días)
└─ Verificación: Stats coinciden con banda de pendientes

Revisor Solicitudes Pendientes
├─ Tabla listando solicitudes en estado 'enviada'
├─ Columnas: Folio, Título, Institución, Fecha, Botón "Revisar"
├─ Click "Revisar" → /revisor/solicitudes/{id}
└─ Detail page carga:
   ├─ Información solicitud (read-only)
   ├─ Campos dinámicos (read-only)
   ├─ Documentos adjuntos: botones Ver Previo + Descargar
   ├─ Sección "Observaciones" para agregar
   └─ Botones "Aprobar" (verde) y "Observar" (amarillo)

Generar Observación
├─ Modal "Agregar Observación"
│  ├─ Dropdown seleccionar Campo (del programa)
│  ├─ Dropdown seleccionar Tipo (documental, técnica)
│  ├─ Textarea comentario (max 500 chars)
│  └─ Botón "Agregar"
├─ Tabla de observaciones actuales
│  ├─ Mostrar: Campo, Tipo (color badge), Comentario
│  └─ Botón delete (rojo X icon)
├─ Botón "Guardar Observaciones"
│  └─ POST /revisor/solicitudes/{id}/observar con lista de obs
│     → Solicitud estado → 'observada'
│     → Notificación enviada a solicitante
│     → Toast "Observaciones guardadas"
└─ Solicitud "Subsanada"
   ├─ Solicitante reenvía para revisión (POST /reenviar)
   ├─ Revisor ve nuevamente en pendientes
   ├─ Verifica cambios, botón "Aprobar"
   └─ POST /revisor/solicitudes/{id}/aprobar
      → Estado → 'en_evaluacion'
      → Notificación: "Solicitud aprobada, pasando a evaluación"

Revisor Bandeja Completadas
├─ Vista de solicitudes aprobadas
├─ Tabla: Folio, Título, Institución, Fecha Aprobación
└─ No actions (read-only)
```

**Verification:**
- [ ] Dashboard stats load correctly and match pendientes count
- [ ] Pendientes tabla lista solo solicitudes en 'enviada'
- [ ] Click revisar → detail page carga completamente
- [ ] Documentos preview/download funcionan
- [ ] Agregar observación modal abre
- [ ] Observación guardada y aparece en tabla
- [ ] Observación delete funciona
- [ ] Guardar observaciones → POST /observar → estado → 'observada'
- [ ] Solicitante puede reenviar si estado='observada'
- [ ] Revisor ve solicitud reenviada nuevamente
- [ ] Aprobar solicitud → POST /aprobar → estado → 'en_evaluacion'
- [ ] Bandeja completadas muestra solicitudes aprobadas
- [ ] Notificaciones enviadas al solicitante

---

### 4. EVALUADOR WORKFLOW ✅ COMPLETE
**Scope:** Evaluate solicitudes → Score criteria → Generate dictamen

#### Scenario: Evaluate Solicitud with Dynamic Criteria
```
Evaluador Dashboard
├─ Estadísticas:
│  ├─ "Por Evaluar" card: count(estado='asignado')
│  ├─ "En Evaluación" card: count(estado='evaluando')
│  └─ "Completadas" card: count(estado='concluido')
└─ Verificación: Stats coinciden con bandeja

Evaluador Bandeja de Evaluaciones
├─ Tabla listando asignaciones
├─ Columnas: Folio, Título, Institución, Estado, Fecha Límite, Acción
├─ Filtrado por estado (dropdown):
│  ├─ "Por Iniciar" → estado='asignado'
│  ├─ "En Progreso" → estado='evaluando'
│  └─ "Evaluadas" → estado='concluido'
├─ Búsqueda por folio/título/institución
├─ Para estado='concluido':
│  ├─ Botón "Descargar Dictamen" (PDF)
│  └─ Click abre descarga de PDF
├─ Para estado='asignado' o 'evaluando':
│  ├─ Botón "Evaluar" (verde)
│  └─ Click → /evaluador/asignaciones/{id}/rubrica
└─ Total mostrado: "{N} evaluaciones asignadas"

Evaluador Rubrica (Evaluación)
├─ Header con info solicitud (read-only)
│  ├─ Folio, Título, Institución, Fecha Límite
│  └─ Estado actual (cambiar a 'evaluando' al abrir)
├─
├─ Sección "Evaluación Técnica" (DUAL-PATH):
│  │
│  ├─ PATH A: Dynamic (Si programa tiene criterios en BD)
│  │  ├─ Para cada criterio:
│  │  │  ├─ Nombre + Descripción
│  │  │  ├─ Input numérico puntaje (0 a puntaje_máximo)
│  │  │  ├─ Live feedback: "{actual}/{máximo} pts"
│  │  │  └─ Color: verde si válido, rojo si excede
│  │  ├─ Ponderación mostrada: "Peso: X%"
│  │  └─ Puntaje total CALCULADO: sum(puntajes obtenidos)
│  │
│  └─ PATH B: Legacy (Si programa NO tiene criterios)
│     ├─ 4 campos hardcodeados:
│     │  ├─ Criterio 1: ___/25 pts
│     │  ├─ Criterio 2: ___/25 pts
│     │  ├─ Criterio 3: ___/25 pts
│     │  └─ Criterio 4: ___/25 pts
│     └─ Puntaje total: sum(4 fields)
│
├─ Total Score Display:
│  ├─ "Puntaje Total: {total}/100"
│  ├─ Color: green si ≥80 (APROBADO), red si <80 (RECHAZADO)
│  └─ Indicador: "Proyecto APROBADO ✓" o "Proyecto RECHAZADO ✗"
│
├─ Sección "Justificación":
│  ├─ Textarea para comentarios (max 2000 chars)
│  └─ Required
│
├─ Sección "Carta de Imparcialidad":
│  ├─ Card con legal text
│  ├─ Checkbox "Certifico bajo protesta de decir verdad que..."
│  ├─ Required para submit
│  └─ Si no checked → error: "Debes aceptar la Carta"
│
├─ Botones:
│  ├─ "Guardar Dictamen" (verde, disabled si form invalid)
│  └─ "Cancelar" (gris)
│
├─ Validaciones:
│  ├─ Frontend:
│  │  ├─ ≥1 criterio puntuado (no todos en 0)
│  │  ├─ Justificación no vacía
│  │  └─ Carta imparcialidad checked
│  └─ Backend (redundante):
│     └─ Same validations, 422 si falla
│
└─ On Submit Success:
   ├─ POST /evaluador/asignaciones/{id}/dictamen
   ├─ Dictamen creado
   ├─ Estado asignación → 'concluido'
   ├─ Solicitud estado → 'aprobada' (si ≥80) o 'rechazada' (si <80)
   ├─ Si aprobada → Ministracion creada (estado 'pendiente')
   ├─ Notificación enviada a solicitante
   ├─ Toast "Dictamen guardado exitosamente"
   └─ Redirect /evaluador/evaluaciones

Evaluador Historico
├─ Vista de evaluaciones completadas (estado='concluido')
├─ Tabla: Folio, Título, Institución, Puntaje, Resultado, Fecha
└─ Click row → muestra detalles del dictamen (read-only)
```

**Verification - Dynamic Path (Si programa.criterios existe):**
- [ ] Rubrica carga todos los criterios del programa
- [ ] Campos input aceptan números 0 a puntaje_máximo
- [ ] Live validación: feedback color roja si excede máximo
- [ ] Puntaje total se calcula automáticamente: sum(criterios)
- [ ] Total score display: verde si ≥80, rojo si <80

**Verification - Legacy Path (Si programa NO tiene criterios):**
- [ ] 4 campos hardcodeados (25 pts each) renderean
- [ ] Inputs aceptan 0-25
- [ ] Puntaje total calcula correctamente: sum(4)
- [ ] Total score display: verde si ≥80, rojo si <80

**Verification - General:**
- [ ] Rubrica abre → estado auto-transiciona a 'evaluando'
- [ ] Justificación requerida, validación live
- [ ] Carta imparcialidad checkbox required
- [ ] Error si no checked → AlertBox rojo
- [ ] Botón Guardar disabled si form inválido
- [ ] Submit éxito → dictamen creado en BD
- [ ] Estado: asignación → 'concluido', solicitud → 'aprobada'/'rechazada'
- [ ] Si aprobada → Ministracion creada
- [ ] Notificación enviada al solicitante
- [ ] Redirect a bandeja éxitoso

---

## 🔍 Database Verification Queries

### Check Convocatoria with TipoPrograma
```bash
php artisan tinker
>>> $conv = Convocatoria::with('tipoPrograma.campos', 'tipoPrograma.documentos', 'tipoPrograma.criterios')->first();
>>> echo "Convocatoria: " . $conv->nombre . PHP_EOL;
>>> echo "TipoPrograma campos: " . $conv->tipoPrograma->campos->count() . PHP_EOL;
>>> echo "TipoPrograma documentos: " . $conv->tipoPrograma->documentos->count() . PHP_EOL;
>>> echo "TipoPrograma criterios: " . $conv->tipoPrograma->criterios->count() . PHP_EOL;
```

### Check Solicitud with Documents
```bash
>>> $sol = Solicitud::with('documentos', 'convocatoria.tipoPrograma.documentos')->first();
>>> echo "Solicitud: " . $sol->folio . PHP_EOL;
>>> echo "Documentos subidos: " . $sol->documentos->count() . PHP_EOL;
>>> echo "Programa documentos requeridos: " . $sol->convocatoria->tipoPrograma->documentos->count() . PHP_EOL;
```

### Check Evaluador Assignment
```bash
>>> $asig = AsignacionEvaluador::with('evaluador', 'solicitud', 'dictamen')->first();
>>> echo "Estado: " . $asig->estado . PHP_EOL;
>>> echo "Evaluador: " . $asig->evaluador->name . PHP_EOL;
>>> echo "Dictamen puntos: " . ($asig->dictamen->puntaje_total ?? 'N/A') . PHP_EOL;
```

---

## 🎯 Critical Edge Cases to Test

### 1. Validación en 2 Capas (GOLD RULE)
- **Frontend bloquea:**
  - Solicitante intenta enviar sin docs obligatorios → AlertBox rojo
  - Evaluador intenta evaluar sin criterios → error visible
- **Backend se defiende:**
  - curl directo sin validación → 422 response
  - Muestra exactamente qué falta

### 2. Array Validation Pattern
- Open DevTools Console
- Verify NO errors: "X.map is not a function"
- All pages defensively handle null/undefined arrays

### 3. API Response Structure Consistency
- `/revisor/solicitudes/pendientes` → array directo `[{...}]`
- `/revisor/stats` → object `{nuevos: X, ...}`
- Frontend parses both correctly

### 4. State Transitions
- Solicitud: borrador → enviada → (observada) → en_evaluacion → aprobada/rechazada
- AsignacionEvaluador: asignado → evaluando → concluido
- Verify NO invalid transitions (e.g., rechazada → enviada)

### 5. File Storage and Access
- Upload PDF → `storage/app/public/documentos/{id}/{filename}`
- Preview iframe: `http://localhost:8000/storage/documentos/{id}/{filename}` ✓
- NOT `http://localhost:3000/storage/...` (wrong port)

---

## 📊 Success Criteria

**MVP is "Complete" when:**
- ✅ Admin: Can create full convocatoria with 7-step wizard
- ✅ Solicitante: Can submit solicitud with all documents
- ✅ Revisor: Can review and add observaciones
- ✅ Evaluador: Can evaluate with dynamic/legacy criteria
- ✅ All 4 workflows connected end-to-end
- ✅ Database persists all data correctly
- ✅ No JavaScript errors in console (except warnings)
- ✅ All validations in 2 layers (frontend + backend)
- ✅ Notifications working
- ✅ State transitions correct

**Red Flags (Stop and Debug):**
- ❌ "TypeError: X is not an object" → Array validation missing
- ❌ "POST returns 422 with error" → Backend validation, check response
- ❌ "Document 403 Forbidden" → Storage disk issue (public vs local)
- ❌ "Form submit silently fails" → No error handling visible
- ❌ "Estado doesn't change" → State transition logic issue

---

## 📝 Post-MVP Priorities

Once all scenarios pass:

1. **Performance Testing:**
   - Load test with 100+ solicitudes
   - Check API response times
   - Cache effectiveness

2. **Security Testing:**
   - SQL injection attempts (handled by Eloquent)
   - CSRF (handled by middleware)
   - 403 Forbidden on unauthorized access
   - JWT token expiration

3. **UI/UX Polish:**
   - Responsive design on mobile
   - Loading states (spinners)
   - Error messages clarity
   - Accessibility (keyboard nav, screen readers)

4. **Documentation:**
   - API documentation (Swagger/OpenAPI)
   - Frontend component storybook
   - Deployment guides

---

**Next Step:** Start with **Admin Workflow** scenario. Verify 7-step wizard completes successfully, then follow with remaining roles.

**Questions During Testing?**
- Check CLAUDE.md for patterns and troubleshooting
- Check MEMORY.md for known issues
- Consult error stack traces for root cause
