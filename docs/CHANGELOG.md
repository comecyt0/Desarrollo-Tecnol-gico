# 📜 Changelog — COMECYT

Todos los cambios significativos del sistema se documentan aquí.

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/).
Versionado según [Semantic Versioning](https://semver.org/lang/es/).

---

## [8.2.2] — 2026-06-25 (cambio de dominio + cert autofirmado confiable)

### Dominio
- Dominio institucional definitivo: `comecyt-sistemas.edomex.gob.mx`
- Reemplazado en: `apps/api/.env`, `apps/web/.env.local`, `web.config`, `emitir-cert-letsencrypt.ps1`, `hosts`, docs y todos los archivos de configuración
- IIS: bindings `http/*:80` y `https/*:443` movidos al nuevo dominio; bindings del dominio anterior eliminados
- Redirección `localhost` → `https://comecyt-sistemas.edomex.gob.mx` verificada (302)
- Next.js reconstruido con `NEXT_PUBLIC_*` baked al nuevo dominio

### Certificado TLS (temporal hasta Let's Encrypt)
- Cert autofirmado generado con `New-SelfSignedCertificate` para `comecyt-sistemas.edomex.gob.mx` + `localhost` (válido 2 años)
- Hash: `2e676a4963e3f4b5836852df17c1c7eb88a684ad`
- Registrado en `http.sys` vía `netsh http add sslcert` para ambos hostnames
- Importado a `cert:\LocalMachine\Root` (Trusted Root CA) → navegadores del servidor muestran candado sin warning
- Al emitir el cert Let's Encrypt (§12, después del DNS), este cert temporal queda reemplazado automáticamente

---

## [8.2.1] — 2026-06-25 (acceso localhost + documentación de cierre)

### IIS — acceso local via `localhost`
- Bindings `http/*:80:localhost` y `https/*:443:localhost` agregados al sitio `comecyt`
- Cert SSL del dominio asignado a `localhost:443` via `netsh http add sslcert`
- Regla `LocalhostRedirect` en `web.config`: `http://localhost` → `https://comecyt-sistemas.edomex.gob.mx`
- Escribir `localhost` en el navegador del servidor abre el sistema (advertencia de cert — normal hasta Let's Encrypt)

### Documentación de cierre
- `WINDOWS_DEPLOYMENT.md` §20 — acceso localhost, comandos de restauración del binding
- `NEXT_STEPS.md` — estado actualizado al 2026-06-25, pendientes externos claros
- `OPERATIONS.md` — Apéndice W con comandos Windows de operación diaria
- `COMECYT_CREDENCIALES.txt` en Desktop del servidor con credenciales de los 4 roles

---

## [8.2.0] — 2026-06-25 (despliegue en producción Windows Server 2022)

> Primera puesta en producción real del sistema en el servidor institucional de COMECYT.
> Stack nativo Windows (sin Docker/VM) por restricción de virtualización anidada en OpenStack.

### Infraestructura desplegada
- **Windows Server 2022** (OpenStack, 8 vCPU, 16 GB RAM) como plataforma de producción
- **IIS 10** + URL Rewrite 2.1 + ARR 3.0 como proxy inverso y terminador TLS
- **PHP 8.4.22** FastCGI bajo IIS (no php-fpm — equivalente funcional en Windows)
- **PostgreSQL 18.3** instalado como servicio Windows desde binarios ZIP
- **Node.js 22** + **Next.js 16 standalone** como servicio NSSM
- **Laravel Reverb** (WebSocket) como servicio NSSM; proxy via ARR con `<webSocket enabled="true" />`
- **NSSM** reemplazando Supervisor: 4 servicios (`comecyt-web`, `comecyt-reverb`, `comecyt-queue`, `comecyt-scheduler`)
- **win-acme** listo para emitir certificado Let's Encrypt cuando el DNS apunte al servidor

### Configuración de seguridad
- `fastcgi.impersonate = 0` en `php.ini` — PHP corre como `IIS APPPOOL\comecyt`, no como IUSR
- ACL en `.env`: solo `SYSTEM` + `Admins` + `IIS APPPOOL\comecyt` tienen acceso
- Config **sin caché** (`config:cache` deshabilitado) porque `HealthController` y `app:deploy-check` usan `env()` directo
- JWT en cookie `comecyt_auth` HttpOnly — nunca expuesto a JS
- 2FA TOTP activo en cuenta admin

### Usuarios creados en BD
- `admin@comecyt.gob.mx` → Administrador (2FA activo)
- `asd@asd.com` → Revisor (prueba)
- `evaluadorr@uaemex.mx` → Evaluador (prueba)
- `solicitante@institucion.mx` → Solicitante (prueba)

### Backups automatizados
- Tarea programada Windows (SYSTEM, 02:00 diario) ejecutando `backup.ps1`
- Retención 14 días, destino `C:\comecyt\backups\`

### Documentación generada
- `docs/WINDOWS_DEPLOYMENT.md` — guía completa §1–§19 (476 líneas)
- `apps/api/public/web.config` — configuración IIS definitiva con WebSocket fix
- `emitir-cert-letsencrypt.ps1` — helper para emisión de cert TLS cuando DNS resuelva

### Problemas resueltos durante el despliegue
- PHP exit 53 (`0xC0000135`) → instalación de VC++ 2015-2022 Redistributable
- Composer fallaba con "requires php >=8.4" → README indicaba 8.2+ pero Symfony 8 exige 8.4
- NSSM servicios Running pero puertos sin escuchar → `AppParameters` debe setearse separado del `install`
- IIS 500.19 Win32=33 → secciones bloqueadas en `applicationHost.config`; fix: `appcmd unlock`
- API 500 tras hardening ACL → `fastcgi.impersonate=1` hacía correr PHP como IUSR sin permisos; fix: `impersonate=0`
- WebSocket `wss://` rechazado (`Connection header invalid`) → `<webSocket enabled="false"/>` deshabilitaba el módulo que ARR necesita para el handshake 101
- `config:cache` rompía health check → `env()` retorna vacío cuando config está cacheada; solución: no cachear

### Pendientes a la fecha 2026-06-25
- DNS `comecyt-sistemas.edomex.gob.mx` → IP pública (en manos de Infra)
- Certificado TLS Let's Encrypt (post-DNS)
- SMTP para notificaciones
- Imágenes del carrusel de login (vía panel admin)
- Logo oficial Estado de México (branding dual deshabilitado temporalmente)
- Visibilidad del repo (actualmente público — revisar con dirección institucional)

---

## [8.1.0] — 2026-06-14 (cierre operativo)

### CI/CD
- **`.github/workflows/tests.yml`**: Pest backend (con servicio PostgreSQL 18) + Vitest + TypeScript check + Next build con webpack
- **`.github/workflows/release.yml`**: Tag `vX.Y.Z` genera GitHub Release con changelog por categoría (feat/sec/fix/docs/chore) + abre issue de deploy checklist automáticamente

### GitHub project hygiene
- **`.github/CODEOWNERS`**: Auto-asignación de reviewers; rutas sensibles (middleware, auth, env, deploy) requieren revisión específica
- **`.github/ISSUE_TEMPLATE/`**: Forms estructurados (bug_report, feature_request) + `config.yml` que bloquea issues en blanco y redirige reportes de seguridad a SECURITY.md
- **`.github/pull_request_template.md`**: Checklist completo del autor (commits, tests, docs, lint)

### Legal e institucional
- **`LICENSE`**: Licencia de Uso Institucional COMECYT (8 secciones: propiedad intelectual, usos permitidos/prohibidos, componentes de terceros, cumplimiento LFPDPPP/LGPDPPSO, exención de garantías, jurisdicción mexicana)
- **`apps/web/src/app/(legal)/privacidad/page.tsx`**: Aviso de Privacidad Integral LFPDPPP (10 secciones, plantilla técnica pendiente de aprobación por Unidad de Transparencia)
- **`apps/web/src/app/(legal)/terminos/page.tsx`**: Términos y Condiciones de Uso (13 secciones, plantilla pendiente de aprobación jurídica)
- Ambas páginas servidas como estáticas (○) por Next.js, fuera de la auth

### Developer Experience
- **`.gitattributes`**: Normalización LF universal, binarios marcados, export-ignore para `git archive`, linguist-language overrides
- **`.editorconfig`**: 4 spaces PHP, 2 spaces TS/JS/CSS/MD, LF universal, UTF-8 — consistencia entre editores
- **`README.md` raíz reescrito**: Navegación por audiencia con tabla de documentación, badges de stack (PHP/Laravel/Next/PostgreSQL/Audited), Quick Start dev separado de DEPLOYMENT.md, métricas reales del repo

### Documentación
- **`docs/NEXT_STEPS.md`**: Checklist accionable para el TIC con 6 bloques (urgente / importante / deploy / post-deploy / primer mes / limpieza local) y comandos exactos
- **`docs/POST_AUDIT_SUMMARY.md`**: Resumen ejecutivo de la sesión 2026-06-09 → 2026-06-14 con métricas antes/después, decisiones técnicas, errores históricos y lecciones

### Verificación
- ESLint: 0 errores
- Vitest: 96/96 tests
- Next build (webpack): 23 páginas estáticas (incluye /privacidad y /terminos)
- PHP lint: 0 errores en 36 modelos

---

## [8.0.0] — 2026-06-12

### Seguridad (✨ post-auditoría completa)

Aplicación de la skill `ciberseguridad-auditor-integral v2.0`:

#### Bloqueantes resueltos (SEV-1)
- **Solicitud y 16 modelos endurecidos** con denylist `$guarded` robusta — bloquea mass-assignment de `estado`, `folio`, `notas_internas`, `en_lista_negra`, timestamps. Defensa en profundidad sobre los `$request->validate()` de los controllers
- **CSP estricto** en `next.config.ts` (frame-ancestors 'none', object-src 'none', default-src 'self'), HSTS 1y, Permissions-Policy granular, X-Frame-Options DENY
- **`images.remotePatterns`** + allowlist de hostnames en `CarouselController` (mitiga SSRF en el carrusel público de login)
- **`deploy.sh` hard-fail** si `APP_DEBUG≠false`, `APP_ENV≠production`, o `DB_PASSWORD` débil/default
- **`.env.example`** con defaults seguros (`APP_ENV=production`, `APP_DEBUG=false`, `HASH_DRIVER=argon2id`)

#### Altos resueltos (SEV-2)
- **CORS estricto en producción** — `config/cors.php` solo lee CSV explícito; `DeployCheck` falla si vacío, contiene `*` o `localhost`
- **Permisos restrictivos en `storage/`** — `755` base + `775` selectivo solo en carpetas de escritura runtime + `chown www-data`
- **Sentry PII scrubbing** en `sentry.{client,server}.config.ts` — redacta agresivamente email, RFC, CURP, CLABE, JWT, Bearer tokens (LFPDPPP-compliant)
- **0 dependencias CDN externas** verificado — todo bundled por Next

#### Medios resueltos (M1-M6)
- **JWT TODO estructurado** en `config/jwt.php` para migración futura a RS256/HSM
- **Argon2id como driver default** de hashing (verificado en runtime con PHP 8.4)
- **ESLint security** + **no-unsanitized** plugins instalados con 14 reglas
- **Dependabot** semanal con grouping inteligente (react-ecosystem, sentry, jwt-auth, etc.)
- **`.github/workflows/security-sast.yml`** — Semgrep + Gitleaks + composer/npm audit
- **`.github/workflows/sbom-release.yml`** — CycloneDX + SPDX en cada release

#### Bajos resueltos (B1-B5)
- **`HealthController`** + ruta `/api/health` protegida con `X-Health-Token` timing-safe, fail-secure 404
- **`docs/security/incident-response.md`** (372 líneas) — runbook IR completo con 4 playbooks + plan rotación de secretos
- **Migración legacy documentada** — `factura_institucion_url` mantiene nombre por compatibilidad con archivos existentes
- **`next.config.ts` con `output: 'standalone'`** — preparado para Docker futuro
- Plan calendarizado de rotación de secretos en runbook

### Documentación
- Nueva carpeta `docs/` con guías estructuradas por audiencia:
  - `DEPLOYMENT.md` — guía maestra (60-90 min)
  - `OPERATIONS.md` — manual operativo
  - `USER_GUIDE.md` — guía por rol
  - `API.md` — referencia de endpoints
  - `ARCHITECTURE.md` — decisiones técnicas
  - `DATABASE.md` — schema BD
  - `DEVELOPMENT.md` — setup dev local
  - `TESTING.md` — guía de tests
  - `CONTRIBUTING.md` — cómo contribuir
  - `security/SECURITY.md` — política de seguridad
  - `security/incident-response.md` — runbook IR
  - `deploy-templates/` — nginx + supervisor confs

### Limpieza
- **`STITCH_API_KEY` removida del histórico completo** con `git-filter-repo` — reescritos 51 commits para borrar archivos del MCP de Claude Code
- `.mcp.json`, `_agents/`, `STITCH_SETUP.md` agregados a `.gitignore`
- Repositorio publicado en `github.com/comecyt0/Desarrollo-Tecnol-gico` con histórico limpio

### Stack actualizado
- **Next.js** 16.2.0 → 16.2.9 (parche de seguridad postcss)
- **17 vulnerabilidades npm resueltas** vía `npm audit fix` (1 critical + 6 high + 10 moderate → 4 moderate transitivas restantes en postcss anidado de Next, sin breaking fix)

---

## [7.0.0] — 2026-04-14 (Lotes 1-8)

### Lote 1 — Branding institucional
- Renombre del sistema a "Gestión De Proyectos de Desarrollo Tecnológico y Vinculación"
- "Aportación mínima" → "Aportación Concurrente"
- "Beca" → "Apoyo"

### Lote 2 — Institución → Empresa
- Tabla `instituciones` renombrada a `empresas` en migración masiva
- FKs `institucion_id → empresa_id` en `solicitudes`, `users`, `evaluadores`, `lista_negra`
- 68 archivos PHP/TS actualizados
- Backend mantiene aliases legacy donde es necesario para retrocompat

### Lote 3 — Registro de postulantes extendido
- Wizard de 5 pasos (`/solicitar-acceso`): Cuenta → Empresa → Contactos → Detalles → Términos
- 4 contactos por solicitud (Responsable obligatorio + Legal, Administrativo, Técnico opcionales)
- Datos de empresa: RFC, tipo persona (física/moral/asociación civil/otro), rol de supervisión
- Aceptación obligatoria de T&C con link a `apoyoempresarial_comecyt.edomex.gob.mx`

### Lote 4 — Categorías de apoyo editables
- Tabla `categorias_apoyo` con seed inicial (Fomento, Talento, Otra)
- `convocatorias.categoria_id` FK
- Modalidad "No aplica" agregada al catálogo

### Lote 5 — Cards modernas (6 tablas refactorizadas)
- Componente genérico `DataCardGrid<T>` con `accentColor` dinámico
- 6 vistas refactorizadas: empresas, usuarios, lista-negra, solicitudes, convenios, ministraciones
- Hover lift con spring physics + glow shadows por color

### Lote 6 — Vista previa lateral en wizard de convocatorias
- `ConvocatoriaPreviewPanel` con sticky position
- Actualización en vivo conforme el admin llena el wizard
- Visible solo en pantallas ≥ 1024px

### Lote 7 — Alertas de cierre + admin doc completo
- Comando `convocatorias:notificar-cierre --dias=N` (T-7, T-3, T-1)
- Schedule `dailyAt('08:00')` en `routes/console.php`
- Notificación `ConvocatoriaCierreProximo` (mail + database) con urgencia escalonada
- Endpoint `GET /admin/solicitudes/{id}/full` con eager-loading TOTAL de relaciones

### Lote 8 — Animaciones suaves
- `glow-fade` keyframe reemplaza pulse en dot del carrusel
- `bounce-soft` reemplaza rotación 360° en bell de notificaciones
- `skeleton-shimmer` reemplaza pulse en estados de carga

---

## [6.0.0] — 2026-03-30 (Arquitectura dinámica)

### Sistema 100% DB-driven
- 13 migraciones nuevas para schema dinámico de programas
- 11 modelos nuevos: `TipoPrograma`, `ProgramaModalidad`, `ProgramaEtapa`, `ProgramaCampo`, `ProgramaDocumento`, `ProgramaRubro`, `ProgramaCriterioEvaluacion`, `SolicitudCampoDinamico`, `SolicitudCriterioEvaluacion`, `SolicitudMiembroEquipo`, `SolicitudRubroPresupuesto`
- 5 programas configurados: PFPI, PROT, IPFE, VINC, EMP
- 480 registros base
- `ProgramaCatalogController` con 8 endpoints públicos
- Cache 5min TTL, granular por programa, zero N+1
- **Restricción crítica cumplida:** agregar programa nuevo = INSERT en BD, CERO cambios de código

---

## [5.0.0] — 2026-03-15 (2FA + admin tools)

- TOTP via Google Authenticator/Authy
- Códigos de recuperación
- `/admin/audit-log` con filtros por usuario/acción/fecha
- `/admin/reset-requests` flow mediado por admin

---

## [4.0.0] — 2026-02-20 (Lista Negra + ministraciones)

- Sistema de sanciones a empresas
- Flujo de ministraciones (CLABE, banco, titular)
- Generación de PDFs (convenio, dictamen)

---

## [3.0.0] — 2026-01-30 (Evaluación dinámica)

- Rúbrica configurable por convocatoria
- Carta de imparcialidad
- Dictámenes con cálculo ponderado

---

## [2.0.0] — 2025-12-15 (Solicitudes + revisor)

- CRUD de solicitudes
- Estados con transiciones validadas
- Panel del revisor con flujo aprobar/observar

---

## [1.0.0] — 2025-11-01 (MVP)

- Auth con JWT en cookie HttpOnly
- 4 roles (admin, revisor, evaluador, solicitante)
- CRUD de convocatorias básicas
- Sistema de notificaciones via NotificacionLog

---

## Convenciones de versionado

- **MAJOR** (X.0.0): cambios incompatibles del API o BD que requieren acción manual
- **MINOR** (X.Y.0): funcionalidad nueva retrocompatible
- **PATCH** (X.Y.Z): fixes y mejoras menores retrocompatibles

Para sec/CVE críticos, hot-fixes en PATCH **independientemente del cronograma de versiones**.

---

> Para ver commits específicos: `git log --oneline --no-decorate` o vista en GitHub.
