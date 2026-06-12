# 🗄️ Esquema de Base de Datos — COMECYT

> **Motor:** PostgreSQL 18
> **Migraciones:** 59 archivos en `apps/api/database/migrations/`
> **Modelos Eloquent:** 36 en `apps/api/app/Models/`
> **Última revisión:** 2026-06-12

---

## 1. Vista general de tablas

```
Identidad/Acceso          Catálogos               Proyectos              Programas (dinámicos)
─────────────────         ──────────              ─────────              ───────────────────
users                     roles                   solicitudes            tipos_programa
solicitudes_acceso        municipios              solicitud_documentos   programa_modalidades
password_reset_requests   bancos                  solicitud_campos_      programa_etapas
audit_logs                areas_conocimiento      dinamicos              programa_campos
empresas                  categorias_apoyo        solicitud_rubros_      programa_documentos
                                                  presupuesto            programa_rubros
                                                  solicitud_miembros_    programa_criterios_
                                                  equipo                 evaluacion
                                                  solicitud_criterios_
                                                  evaluacion

Convocatorias             Evaluación              Flujo financiero       Comunicación
─────────────────         ──────────              ──────────────         ────────────
convocatorias             asignaciones_evaluador  convenios              notificaciones_log
                          dictamenes              ministraciones         push_subscriptions
                          observaciones           informes               user_preferences
                          lista_negra             comprobantes           carousel_slides
```

**Total: ~38 tablas** + tablas de framework (`migrations`, `password_reset_tokens`, `failed_jobs`, `jobs`, `cache`, `sessions`).

---

## 2. Modelo entidad-relación (textual)

```
roles (4 rows: admin, revisor, evaluador, solicitante)
  └─ 1:N users

users (cuentas)
  ├─ N:1 roles
  ├─ N:1 empresas (solicitantes)
  ├─ 1:N solicitudes (como autor)
  ├─ 1:N asignaciones_evaluador (como evaluador)
  ├─ 1:N notificaciones_log
  ├─ 1:N audit_logs
  ├─ 1:1 user_preferences
  └─ 1:N push_subscriptions

empresas
  ├─ N:1 municipios
  ├─ 1:N users (solicitantes)
  ├─ 1:N solicitudes (institucion_id / empresa_id)
  └─ 1:N lista_negra

tipos_programa (uno por convocatoria, 1:1)
  ├─ 1:N programa_modalidades
  ├─ 1:N programa_etapas
  ├─ 1:N programa_campos (campos dinámicos del formulario)
  ├─ 1:N programa_documentos (docs requeridos)
  ├─ 1:N programa_rubros (rubros de presupuesto)
  ├─ 1:N programa_criterios_evaluacion (rúbrica)
  └─ 1:1 convocatorias

categorias_apoyo (Fomento, Talento, Otra, +editables)
  └─ 1:N convocatorias

convocatorias
  ├─ 1:1 tipos_programa
  ├─ N:1 categorias_apoyo
  └─ 1:N solicitudes

solicitudes (corazón del sistema)
  ├─ N:1 convocatorias
  ├─ N:1 users (autor)
  ├─ N:1 empresas
  ├─ N:1 areas_conocimiento (nullable)
  ├─ N:1 programa_modalidades
  ├─ N:1 programa_etapas (etapa_actual_id)
  ├─ 1:N solicitud_documentos
  ├─ 1:N solicitud_campos_dinamicos (respuestas a campos custom)
  ├─ 1:N solicitud_rubros_presupuesto
  ├─ 1:N solicitud_miembros_equipo
  ├─ 1:N solicitud_criterios_evaluacion
  ├─ 1:N observaciones (del revisor)
  ├─ 1:N asignaciones_evaluador
  ├─ 1:1 convenios
  ├─ 1:1 ministraciones
  ├─ 1:N informes (intermedio, final)
  └─ 1:N notificaciones_log

asignaciones_evaluador
  ├─ N:1 solicitudes
  ├─ N:1 users (evaluador)
  ├─ N:1 users (asignado_por = admin)
  └─ 1:1 dictamenes

dictamenes
  ├─ 1:1 asignaciones_evaluador
  └─ 1:N solicitud_criterios_evaluacion (calificaciones)

ministraciones
  ├─ 1:1 solicitudes
  ├─ N:1 bancos
  └─ 1:N comprobantes (cuando hay pago)

informes
  └─ N:1 solicitudes

lista_negra (sanciones)
  ├─ N:1 empresas
  ├─ N:1 solicitudes (qué proyecto causó la sanción)
  └─ N:1 users (sancionado_por)
```

---

## 3. Tablas de identidad

### `roles`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | 1=admin, 2=revisor, 3=evaluador, 4=solicitante |
| `slug` | varchar | unique: admin, revisor, evaluador, solicitante |
| `nombre` | varchar | display name |
| `descripcion` | text | |

> Tabla inmutable en producción (seedeada). Modelo `Rol::class` con `$guarded = ['id', 'created_at', 'updated_at']`.

### `users`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `rol_id` | FK → roles | NOT NULL |
| `empresa_id` | FK → empresas | nullable (admins/revisores/evaluadores no tienen empresa) |
| `name` | varchar | |
| `email` | varchar UNIQUE | |
| `password` | varchar | Argon2id hash (60+ chars) |
| `email_verified_at` | timestamp | |
| `activo` | boolean | soft-disable de cuentas |
| `two_factor_secret` | text encrypted | secreto TOTP |
| `two_factor_recovery_codes` | text encrypted | códigos de recuperación |
| `two_factor_confirmed_at` | timestamp | NULL si no activado |
| `terminos_aceptados_at` | timestamp | postulantes |
| `remember_token` | varchar | (no usado; JWT) |
| `created_at`, `updated_at` | timestamps | |

### `solicitudes_acceso`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `nombre` | varchar | |
| `email` | varchar UNIQUE | |
| `password` | varchar | hashed; se traspasa a users.password al aprobar |
| `empresa_nombre` | varchar | (renombrado de institucion_nombre) |
| `cargo` | varchar nullable | |
| `telefono` | varchar nullable | |
| `motivo` | text nullable | |
| `empresa_datos` | jsonb | { rfc, tipo_persona, rol_supervision } |
| `contactos` | jsonb | [{ rol, nombre, telefono, correo }, ...] |
| `terminos_aceptados` | boolean | |
| `estado` | enum | pendiente, aprobada, rechazada |
| `motivo_rechazo` | text nullable | |
| `revisado_por` | FK → users | nullable |
| `revisado_at` | timestamp | nullable |

### `audit_logs`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK → users | nullable (acciones del sistema) |
| `action` | varchar | login_success, login_failed, solicitud_aprobada, etc. |
| `subject_type` | varchar nullable | morphTo (`App\Models\Solicitud`, etc.) |
| `subject_id` | bigint nullable | |
| `ip_address` | varchar | |
| `user_agent` | text | |
| `metadata` | jsonb | datos extra |
| `created_at` | timestamp | sin updated_at (WORM) |

> Inmutable por diseño: `$guarded = ['id']` y ningún controller expone update/delete.

---

## 4. Tablas de catálogos

### `empresas`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `nombre` | varchar | |
| `acronimo` | varchar nullable | |
| `tipo` | varchar | publica, privada, centro_investigacion |
| `rfc` | varchar(13) nullable | RFC mexicano |
| `tipo_persona` | varchar(30) nullable | fisica, moral, asociacion_civil, otro |
| `rol_supervision` | text nullable | quién supervisa contratos en la empresa |
| `estado` | varchar | "Estado de México" default |
| `municipio` | varchar nullable | (texto, no FK) |
| `direccion` | varchar nullable | |
| `telefono` | varchar(20) nullable | |
| `correo` | varchar nullable | |
| `representante_legal` | varchar nullable | |
| `activo` | boolean | |
| `en_lista_negra` | boolean | flag denormalizado |
| `deleted_at` | timestamp | soft delete |

> Tabla renombrada de `instituciones` en migración `2026_06_02_120000_rename_instituciones_to_empresas.php`. Las FKs `institucion_id → empresa_id` fueron renombradas en solicitudes, users, evaluadores, lista_negra.

### `municipios`, `bancos`, `areas_conocimiento`

Catálogos pequeños, casi inmutables. Estructura típica:

```
id, nombre, [codigo], [activo], created_at, updated_at
```

### `categorias_apoyo`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `nombre` | varchar | Fomento, Talento, Otra... |
| `reembolsable` | boolean | true para Fomento, false para Talento |
| `descripcion` | text | |
| `orden` | int | display order |
| `activa` | boolean | |

---

## 5. Programas dinámicos (configuración por convocatoria)

### `tipos_programa`

Padre de toda la configuración dinámica. 1:1 con `convocatorias`.

```
id, nombre, slug, descripcion, activo
```

### `programa_modalidades`

Modalidades del programa (Presencial, Virtual, "No aplica", etc.).

```
id, tipo_programa_id (FK), nombre, descripcion, activa, orden
```

### `programa_etapas`

Etapas del flujo dentro de una solicitud (Postulación, Revisión, Convenio, etc.).

```
id, tipo_programa_id (FK), nombre, slug, descripcion, orden
```

### `programa_campos`

Campos personalizados del formulario que llena el solicitante.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tipo_programa_id` | FK | |
| `slug` | varchar | identificador único en el formulario |
| `label` | varchar | display |
| `tipo` | enum | text, textarea, number, date, select, multiselect, file |
| `opciones` | jsonb | para select/multiselect |
| `validacion` | jsonb | rules (required, min, max, regex) |
| `orden` | int | |
| `obligatorio` | boolean | |

### `programa_documentos`

Documentos que el solicitante debe subir.

```
id, tipo_programa_id, slug, label, descripcion, formato (pdf|docx|...), obligatorio, max_size_mb, orden
```

### `programa_rubros`

Conceptos de presupuesto que el solicitante debe presupuestar.

```
id, tipo_programa_id, slug, label, descripcion, porcentaje_maximo, obligatorio, orden
```

### `programa_criterios_evaluacion`

Rúbrica que usa el evaluador.

```
id, tipo_programa_id, slug, label, descripcion, ponderacion (decimal), puntuacion_maxima, orden
```

---

## 6. Convocatorias

### `convocatorias`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `tipo_programa_id` | FK | NOT NULL (1:1) |
| `categoria_id` | FK → categorias_apoyo | |
| `nombre` | varchar | |
| `slug` | varchar UNIQUE | |
| `descripcion` | text | rich text HTML |
| `fecha_inicio` | date | |
| `fecha_cierre` | date | |
| `fecha_publicacion` | date nullable | |
| `monto_minimo` | decimal(10,2) | |
| `monto_maximo` | decimal(10,2) | |
| `aportacion_concurrente_min` | decimal | "Aportación Concurrente" (antes "Aportación mínima") |
| `cupo_maximo` | int nullable | |
| `estado` | enum | borrador, publicada, cerrada, cancelada |
| `imagen_url` | varchar nullable | banner |
| `published_at` | timestamp nullable | |

---

## 7. Solicitudes (corazón del dominio)

### `solicitudes`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `folio` | varchar UNIQUE | autogenerado, NO editable |
| `convocatoria_id` | FK | |
| `user_id` | FK | solicitante |
| `institucion_id` / `empresa_id` | FK → empresas | (renombrada en Lote 2) |
| `area_conocimiento_id` | FK | nullable |
| `modalidad_id` | FK | |
| `etapa_actual_id` | FK | |
| `categoria_id` | FK → categorias_apoyo | |
| `titulo_proyecto` | varchar(255) | |
| `descripcion_proyecto` | text | rich HTML |
| `fecha_inicio_evento` | date nullable | |
| `fecha_fin_evento` | date nullable | |
| `monto_solicitado` | decimal(10,2) | |
| `aportacion_concurrente` | decimal(10,2) | |
| `porcentaje_transporte` | decimal(5,2) | |
| `porcentaje_comida` | decimal(5,2) | |
| `porcentaje_hospedaje` | decimal(5,2) | |
| `estado` | varchar(50) | ver estados arriba |
| `etapa_estado` | varchar | |
| `estado_informe` | varchar | |
| `fecha_entrega_informe` | timestamp nullable | |
| `fecha_limite_informe` | timestamp nullable | |
| `informe_final_url` | varchar nullable | |
| `observaciones_informe` | text nullable | |
| `resultados_obtenidos` | text nullable | |
| `notas_internas` | text nullable | solo admin |
| `deleted_at` | timestamp | soft delete |

> Modelo `Solicitud::class` con `$guarded` denylist bloqueando: `id, folio, estado, notas_internas, etapa_estado, estado_informe, timestamps, deleted_at`.

### `solicitud_documentos`

```
id, solicitud_id (FK), programa_documento_id (FK), nombre_original, nombre_almacenado,
url, tamaño_bytes, mime_type, hash_sha256, created_at
```

> `nombre_almacenado` se genera con `Str::random()` para evitar path traversal.

### `solicitud_campos_dinamicos`

Respuestas a campos personalizados de la convocatoria.

```
id, solicitud_id (FK), programa_campo_id (FK), valor (jsonb)
```

### `solicitud_rubros_presupuesto`

```
id, solicitud_id (FK), programa_rubro_id (FK), monto_solicitado, descripcion
```

### `solicitud_miembros_equipo`

```
id, solicitud_id (FK), nombre, rol, email, telefono, perfil (jsonb)
```

### `solicitud_criterios_evaluacion`

Calificaciones que da el evaluador.

```
id, solicitud_id (FK), programa_criterio_evaluacion_id (FK), asignacion_id (FK),
puntuacion (decimal), comentario (text), created_at
```

---

## 8. Evaluación

### `asignaciones_evaluador`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `solicitud_id` | FK | |
| `evaluador_id` | FK → users | |
| `asignado_por` | FK → users | admin que asignó |
| `estado` | varchar(50) | asignado, evaluando, concluido, cancelado |
| `fecha_limite` | date nullable | |
| `carta_imparcialidad_aceptada` | boolean | |
| | | UNIQUE(solicitud_id, evaluador_id) |

### `dictamenes`

```
id, asignacion_id (FK 1:1), recomendacion (enum: recomendado|no_recomendado),
puntuacion_total (decimal calculado), comentario_general (text), emitido_at
```

### `observaciones`

```
id, solicitud_id (FK), revisor_id (FK), motivo (text), campos (jsonb), created_at
```

### `lista_negra`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `empresa_id` / `institucion_id` | FK → empresas | (renombrada en Lote 2) |
| `solicitud_id` | FK | nullable, qué proyecto causó la sanción |
| `sancionado_por` | FK → users | |
| `motivo` | text | |
| `fecha_inicio_sancion` | date | |
| `fecha_fin_sancion` | date nullable | NULL = indefinido |
| `activa` | boolean | toggle para "remover veto" |

---

## 9. Flujo financiero

### `convenios`

```
id, solicitud_id (FK 1:1), folio, fecha_firma, archivo_url, observaciones,
estado (borrador|firmado|cancelado), created_at
```

### `ministraciones`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `solicitud_id` | FK 1:1 | |
| `banco_id` | FK | nullable |
| `cuenta_clabe` | varchar(18) | CLABE interbancaria MX |
| `numero_cuenta` | varchar(50) | |
| `titular_cuenta` | varchar | |
| `carta_compromiso_url` | varchar nullable | |
| `carta_compromiso_aprobada` | boolean | |
| `caratula_banco_url` | varchar nullable | |
| `constancia_fiscal_url` | varchar nullable | |
| `factura_institucion_url` | varchar nullable | (legacy nombre, ver B1 en CLAUDE.md) |
| `solicitud_pago_oficial_url` | varchar nullable | |
| `estado` | varchar(50) | pendiente, revision, autorizada, pagada, rechazada |
| `observaciones` | text nullable | |

### `comprobantes`

Comprobantes de gasto del solicitante para tracking de uso del dinero.

```
id, ministracion_id (FK), rubro_id (FK), monto, archivo_url, validado_at, validado_por
```

### `informes`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `solicitud_id` | FK | |
| `tipo` | varchar(50) | intermedio, final |
| `fecha_limite_entrega` | date | 20 días hábiles máx |
| `fecha_entregado` | date nullable | |
| `archivo_informe_url` | varchar | |
| `archivo_evidencias_url` | varchar nullable | |
| `resultados_obtenidos` | text nullable | |
| `estado` | varchar(50) | pendiente, en_revision, aprobado, rechazado |
| `observaciones` | text nullable | |

---

## 10. Comunicación

### `notificaciones_log`

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | FK | destinatario |
| `solicitud_id` | FK | nullable |
| `tipo` | varchar | clase de notificación |
| `asunto` | varchar | |
| `mensaje` | text | puede incluir HTML simple |
| `leido_at` | timestamp nullable | |
| `created_at`, `updated_at` | timestamps | |

> Boot hook dispara broadcast (Reverb) + Web Push al crear.

### `push_subscriptions`

Suscripciones de navegadores al Web Push API.

```
id, user_id (FK), endpoint (text UNIQUE), p256dh_key (text), auth_token (text),
user_agent (varchar), created_at
```

### `user_preferences`

```
id, user_id (FK 1:1), notif_email (jsonb), notif_push (jsonb), idioma, tema,
created_at, updated_at
```

### `carousel_slides`

```
id, titulo, subtitulo, descripcion, imagen_url, badge_texto, orden, activo
```

> `imagen_url` validada contra allowlist de hosts en `CarouselController` (anti-SSRF).

---

## 11. Índices recomendados

```sql
-- Solicitudes: lookup por user + estado (dashboards)
CREATE INDEX idx_solicitudes_user_estado ON solicitudes (user_id, estado);
CREATE INDEX idx_solicitudes_convocatoria_estado ON solicitudes (convocatoria_id, estado);

-- Audit log: queries por user en ventana de tiempo
CREATE INDEX idx_audit_user_created ON audit_logs (user_id, created_at DESC);
CREATE INDEX idx_audit_action ON audit_logs (action);

-- Asignaciones evaluador: dashboard del evaluador
CREATE INDEX idx_asignaciones_eval_estado ON asignaciones_evaluador (evaluador_id, estado);

-- Notificaciones: bandeja del usuario
CREATE INDEX idx_notif_user_leido ON notificaciones_log (user_id, leido_at);

-- Empresas: búsqueda por nombre/RFC
CREATE INDEX idx_empresas_nombre_trgm ON empresas USING gin (nombre gin_trgm_ops);
CREATE INDEX idx_empresas_rfc ON empresas (rfc);

-- Solicitudes: búsqueda por folio
CREATE UNIQUE INDEX idx_solicitudes_folio ON solicitudes (folio);
```

La mayoría ya están creados en las migraciones. `pg_trgm` requiere `CREATE EXTENSION pg_trgm;` (opcional, para fuzzy search).

---

## 12. Migraciones críticas (orden histórico relevante)

```
2026_03_18_100010_create_instituciones_table       # tabla base de empresas (antes "instituciones")
2026_03_18_*                                       # bloques base de schema
2026_03_30_22*                                     # 13 migraciones de schema dinámico
2026_06_02_120000_rename_instituciones_to_empresas # Lote 2: rename masivo
2026_06_02_130000_add_postulante_fields             # Lote 3: contactos, empresa_datos
2026_06_02_140000_rename_institucion_nombre...     # SolicitudAcceso campo
2026_06_02_150000_create_categorias_and_link       # Lote 4: categorias_apoyo
```

`migrations` table de Laravel registra cuáles han corrido. `php artisan migrate:status` muestra estado.

---

## 13. Backups

Ver `docs/OPERATIONS.md §6` — pg_dump diario automatizado con retención 30 días + cron sync a backup off-site.

---

## 14. Comandos útiles para inspección de DB

```bash
# Conectar
sudo -u postgres psql comecyt_prod

# Tamaño total
SELECT pg_size_pretty(pg_database_size('comecyt_prod'));

# Tablas más grandes
SELECT schemaname, tablename,
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS size
FROM pg_tables WHERE schemaname='public'
ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC LIMIT 10;

# Queries lentas (requiere pg_stat_statements)
SELECT query, calls, mean_exec_time, total_exec_time
FROM pg_stat_statements ORDER BY mean_exec_time DESC LIMIT 10;

# Solicitudes por estado
SELECT estado, COUNT(*) FROM solicitudes WHERE deleted_at IS NULL GROUP BY estado;

# Top usuarios por actividad
SELECT u.email, COUNT(al.id) AS acciones
FROM audit_logs al JOIN users u ON u.id = al.user_id
WHERE al.created_at >= now() - interval '7 days'
GROUP BY u.email ORDER BY 2 DESC LIMIT 20;
```

---

## 15. Restricciones de integridad

| Tabla | Restricción | Nivel |
|---|---|---|
| `users.email` | UNIQUE | DB constraint |
| `solicitudes.folio` | UNIQUE | DB constraint |
| `asignaciones_evaluador (solicitud_id, evaluador_id)` | UNIQUE compound | DB |
| `convocatorias.slug` | UNIQUE | DB |
| `solicitudes.estado` | ENUM check | DB constraint |
| `lista_negra.activa` | Solo 1 activa por empresa | Application-level |
| `ministraciones.cuenta_clabe` | 18 dígitos | Validation en controller |
| `empresas.rfc` | Formato MX | Validation regex |

---

> Para diff de schema entre versiones, usar `php artisan schema:dump` o `pg_dump --schema-only`.
