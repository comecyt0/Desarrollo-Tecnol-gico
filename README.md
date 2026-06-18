# 🚀 COMECYT — Gestión de Proyectos de Desarrollo Tecnológico y Vinculación

![Status](https://img.shields.io/badge/status-desplegado-success)
![Version](https://img.shields.io/badge/version-8.2.0-blue)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20)
![Next.js](https://img.shields.io/badge/Next.js-16.x-000000)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-18-336791)
![Audited](https://img.shields.io/badge/security-auditado-success)

Sistema institucional del **Consejo Mexiquense de Ciencia y Tecnología (COMECYT)** para administrar el ciclo completo de convocatorias de financiamiento a empresas y proyectos de desarrollo tecnológico:

```
publicación → postulación → revisión → evaluación → convenio → ministración → seguimiento → cierre
```

---

## 📚 Documentación

| Tu objetivo | Lee |
|---|---|
| 🚀 **Instalar en servidor Linux nuevo** | [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — guía completa 60–90 min + `bootstrap.sh` |
| 🪟 **Instalar en Windows Server** | `docs/WINDOWS_DEPLOYMENT.md` (branch `windows-deployment`) — IIS+ARR sin WSL |
| 🛠️ **Operar el sistema día a día** | [`docs/OPERATIONS.md`](docs/OPERATIONS.md) — comandos, monitoreo, backups |
| 👥 **Usar el sistema (por rol)** | [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md) — admin, revisor, evaluador, solicitante |
| 💻 **Desarrollar localmente** | [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) — setup + workflow |
| 🤝 **Contribuir con PRs** | [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) — flujo + reglas |
| 🏗️ **Entender la arquitectura** | [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — decisiones técnicas |
| 🗄️ **Ver el schema de la BD** | [`docs/DATABASE.md`](docs/DATABASE.md) — 38 tablas documentadas |
| 🔌 **Consultar el API** | [`docs/API.md`](docs/API.md) — 126 endpoints |
| 🧪 **Correr tests** | [`docs/TESTING.md`](docs/TESTING.md) — Pest, Vitest, Playwright |
| 🔒 **Reportar vulnerabilidad** | [`docs/security/SECURITY.md`](docs/security/SECURITY.md) — política + cómo reportar |
| 🚨 **Responder a un incidente** | [`docs/security/incident-response.md`](docs/security/incident-response.md) — runbook IR |
| 📜 **Ver historial de versiones** | [`docs/CHANGELOG.md`](docs/CHANGELOG.md) — v1.0 → v8.1 |
| ✅ **Terminar el deploy (TIC)** | [`docs/NEXT_STEPS.md`](docs/NEXT_STEPS.md) — checklist con tiempos |
| 📊 **Resumen ejecutivo de la auditoría** | [`docs/POST_AUDIT_SUMMARY.md`](docs/POST_AUDIT_SUMMARY.md) |

**Índice maestro:** [`docs/README.md`](docs/README.md)

---

## ⚡ Quick Start (DEV LOCAL)

> Para deploy en producción, ver [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

### Prerequisitos

- PHP 8.2+ con extensiones `pgsql`, `mbstring`, `sodium`
- Composer 2.x
- Node.js 20 LTS
- PostgreSQL 18

### Setup

```bash
# 1. Clone
git clone https://github.com/comecyt0/Desarrollo-Tecnol-gico.git comecyt
cd comecyt

# 2. Base de datos local
sudo -u postgres psql <<'SQL'
CREATE USER comecyt WITH PASSWORD 'comecyt_dev_password';
CREATE DATABASE comecyt_dev OWNER comecyt ENCODING 'UTF8';
GRANT ALL PRIVILEGES ON DATABASE comecyt_dev TO comecyt;
SQL

# 3. Backend
cd apps/api
cp .env.example .env
# Edita DB_PASSWORD, APP_ENV=local, APP_DEBUG=true
composer install
php artisan key:generate
php artisan jwt:secret
php artisan storage:link
php artisan migrate --seed

# 4. Frontend
cd ../web
cp .env.example .env.local
# Edita NEXT_PUBLIC_API_URL=http://localhost:8000/api
npm install --legacy-peer-deps
```

### Arrancar (2 terminales)

```bash
# Terminal 1
cd apps/api && php artisan serve         # → http://localhost:8000

# Terminal 2
cd apps/web && npm run dev               # → http://localhost:3000
```

### Login de prueba (solo dev local)

| Rol | Email | Password |
|------|-------|----------|
| Admin | `admin@comecyt.gob.mx` | `password123` |
| Revisor | `asd@asd.com` | `password123` |
| Evaluador | `evaluadorr@uaemex.mx` | `password123` |
| Solicitante | `solicitante@institucion.mx` | `password123` |

> ⚠️ Estos usuarios **NO se crean en producción** — el seeder `UsuariosPruebaSeeder` se omite automáticamente cuando `APP_ENV=production`.

---

## 🏗️ Stack

| Capa | Tecnología |
|---|---|
| Frontend | Next.js 16 (App Router) + React 19 + TypeScript |
| Backend | Laravel 11 (API-only) |
| BD | PostgreSQL 18 |
| Estilos | Tailwind v4 + Shadcn UI v3 |
| Auth | JWT HS256 en HttpOnly cookie + 2FA TOTP |
| Real-time | Laravel Reverb (WebSocket) |
| Hashing | Argon2id |
| Hosting | nginx + php-fpm + Supervisor (bare-metal) |
| CI/CD | GitHub Actions (Semgrep, Gitleaks, Dependabot, SBOM) |

Detalles en [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md).

---

## 🛡️ Seguridad

Sistema auditado con la skill `ciberseguridad-auditor-integral v2.0`:

- ✅ 17 modelos endurecidos contra mass-assignment
- ✅ Rate limiting de 5 capas (IP, email, global, endpoint, gateway DDoS)
- ✅ CSP estricto + HSTS + headers seguros
- ✅ CORS estricto en producción (fail-fast en deploy)
- ✅ PII scrubbing en Sentry (LFPDPPP-compliant)
- ✅ File upload con validación MIME real (finfo)
- ✅ Allowlist de hostnames para imágenes (anti-SSRF)
- ✅ Dependabot + Semgrep + Gitleaks en CI
- ✅ SBOM (CycloneDX + SPDX) por release
- ✅ Runbook IR + plan de rotación de secretos

Para reportar vulnerabilidades: [`docs/security/SECURITY.md`](docs/security/SECURITY.md).

---

## 📊 Métricas del repo

| Métrica | Valor |
|---|---|
| Modelos Eloquent | 36 |
| Controllers | 20 |
| Migraciones | 59 |
| Endpoints | 126 |
| Workflows CI | 4 (tests, security-sast, sbom-release, release) |
| Documentación | ~4,500 líneas en 14 archivos |

---

## 📋 Licencia

Software propiedad institucional del **Consejo Mexiquense de Ciencia y Tecnología (COMECYT)**, organismo público descentralizado del Gobierno del Estado de México. Ver [`LICENSE`](LICENSE).

Usos permitidos limitados a operación institucional, auditoría autorizada y estudios académicos sin fines de lucro previa autorización.

---

## 🤝 Contribuir

Lee [`docs/CONTRIBUTING.md`](docs/CONTRIBUTING.md) antes de enviar tu primer PR.

Resumen:
1. Conventional Commits (`feat:`, `fix:`, `sec:`, `chore:`, `docs:`)
2. PRs pequeños (< 500 líneas idealmente)
3. Tests para el código nuevo
4. CI debe pasar antes del merge
5. Code review obligatorio

---

## 📞 Contacto

- **Bugs / features:** [GitHub Issues](https://github.com/comecyt0/Desarrollo-Tecnol-gico/issues)
- **Soporte general:** soporte@comecyt.gob.mx
- **Vulnerabilidades de seguridad:** ver [`SECURITY.md`](docs/security/SECURITY.md) (NO Issues públicos)
- **Datos personales / LFPDPPP:** Unidad de Transparencia COMECYT

---

<sub>
**Versión:** 8.2.0 — 2026-06-18<br>
**Estado:** ✅ DESPLEGADO en Windows Server 2022 (IIS+ARR). Sistema funcionando intra-server. Esperando DNS público (Infra) + SMTP (TIC) para apertura al público.<br>
**Próxima revisión obligatoria:** 2026-12-18 (semestral)
</sub>
