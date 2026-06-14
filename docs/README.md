# 📚 Documentación COMECYT

Sistema de Gestión de Proyectos de Desarrollo Tecnológico y Vinculación.

---

## 🧭 Por dónde empezar

### Soy administrador de TIC y voy a **instalar el sistema en un servidor nuevo**
→ **[DEPLOYMENT.md](DEPLOYMENT.md)** — Guía completa de despliegue (60-90 min) + [`deploy-templates/`](deploy-templates/) con confs listas para copiar

### Soy administrador y voy a **operar el sistema día a día**
→ **[OPERATIONS.md](OPERATIONS.md)** — Comandos artisan, backups, troubleshooting, monitoreo

### Soy usuario final (admin, revisor, evaluador, solicitante) del sistema
→ **[USER_GUIDE.md](USER_GUIDE.md)** — Guía paso a paso por cada rol + FAQ

### Soy desarrollador y voy a **modificar el código**
→ **[DEVELOPMENT.md](DEVELOPMENT.md)** — Setup local + workflow git + convenciones
→ **[CONTRIBUTING.md](CONTRIBUTING.md)** — Cómo enviar PRs
→ **[TESTING.md](TESTING.md)** — Tests y CI

### Quiero entender la **arquitectura técnica**
→ **[ARCHITECTURE.md](ARCHITECTURE.md)** — Capas, middleware, decisiones de diseño
→ **[DATABASE.md](DATABASE.md)** — Schema BD con 38 tablas documentadas
→ **[API.md](API.md)** — 126 endpoints catalogados

### Tengo un **incidente de seguridad** o reporto una vulnerabilidad
→ **[security/incident-response.md](security/incident-response.md)** — Runbook IR + LFPDPPP
→ **[security/SECURITY.md](security/SECURITY.md)** — Política + cómo reportar

### Quiero ver qué cambió en cada versión
→ **[CHANGELOG.md](CHANGELOG.md)** — Historial de versiones

### Soy el TIC y necesito saber **qué falta hacer** para terminar el despliegue
→ **[NEXT_STEPS.md](NEXT_STEPS.md)** — Checklist accionable con tiempos estimados

### Necesito el **resumen ejecutivo** de la auditoría de seguridad
→ **[POST_AUDIT_SUMMARY.md](POST_AUDIT_SUMMARY.md)** — Métricas antes/después + decisiones técnicas

---

## 📂 Mapa completo de archivos

```
docs/
├── README.md                       ← Estás aquí
│
├── 🚀 DEPLOYMENT.md                ← Instalar en servidor nuevo
├── 🛠️ OPERATIONS.md                ← Operar día a día
├── 👥 USER_GUIDE.md                ← Guía por rol de usuario
│
├── 🏗️ ARCHITECTURE.md              ← Decisiones técnicas
├── 🗄️ DATABASE.md                  ← Schema completo
├── 🔌 API.md                       ← Endpoints
│
├── 💻 DEVELOPMENT.md               ← Dev local
├── 🤝 CONTRIBUTING.md              ← Cómo contribuir
├── 🧪 TESTING.md                   ← Tests
│
├── 📜 CHANGELOG.md                 ← Versiones 1.0 → 8.1
├── ✅ NEXT_STEPS.md                 ← Checklist para terminar deploy
├── 📊 POST_AUDIT_SUMMARY.md         ← Resumen ejecutivo de la auditoría
│
├── 🔒 security/
│   ├── SECURITY.md                 ← Política + cómo reportar vulns
│   └── incident-response.md        ← Runbook IR + LFPDPPP
│
└── 📦 deploy-templates/            ← Confs listas para copiar
    ├── nginx-comecyt.conf
    ├── supervisor-comecyt-reverb.conf
    ├── supervisor-comecyt-next.conf
    └── supervisor-comecyt-queue.conf
```

---

## ⚡ Stack tecnológico (resumen)

| Capa | Tecnología |
|---|---|
| Frontend | Next.js 16 (App Router) + React 19 + TypeScript |
| Backend | Laravel 11 (API-only) |
| Estilos | Tailwind v4 + Shadcn UI v3 |
| Animaciones | Framer Motion |
| Auth | JWT (HS256, HttpOnly cookie) + 2FA TOTP opcional |
| DB | PostgreSQL 18 |
| WebSocket | Laravel Reverb |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/laravel-excel |
| Email | SMTP (institucional) |
| Push | Web Push API (VAPID) |
| Monitoring | Sentry con PII scrubbing LFPDPPP-safe |
| CI/CD | GitHub Actions (Semgrep, Gitleaks, Dependabot, SBOM) |

Ver detalles completos en [`ARCHITECTURE.md`](ARCHITECTURE.md).

---

## 🏗️ Arquitectura en una página

```
                    HTTPS
                      │
                      ▼
              ┌──────────────┐
              │    nginx     │  ← reverse-proxy + TLS + headers
              └──────┬───────┘
              /api/* │  /*    │ /app/*
                 ▼        ▼        ▼
        ┌──────────┐  ┌──────────┐  ┌──────────┐
        │ Laravel  │  │ Next.js  │  │ Reverb   │
        │ (php-fpm)│  │ (Node)   │  │ (WS)     │
        └─────┬────┘  └──────────┘  └──────────┘
              │
              ▼
        ┌──────────────┐
        │ PostgreSQL 18│
        └──────────────┘

       ┌──────────────┐
       │  Supervisor  │  ← mantiene Reverb + Next + Queue vivos
       └──────────────┘
```

---

## 👥 4 roles del sistema

| Rol | ID | Capacidades clave |
|---|---|---|
| Administrador | 1 | Control total: usuarios, convocatorias, evaluadores, ministraciones, lista negra, auditoría |
| Revisor Documental | 2 | Valida documentación de solicitudes |
| Evaluador Técnico | 3 | Puntúa proyectos según rúbrica dinámica |
| Solicitante (empresa) | 4 | Postula, sube documentos, recibe ministración, entrega informes |

Ver detalles del flujo en [`USER_GUIDE.md`](USER_GUIDE.md).

---

## 🛡️ Postura de seguridad

Sistema auditado con la skill `ciberseguridad-auditor-integral v2.0` (junio 2026):

| Capa | Implementación |
|---|---|
| Headers HTTP | CSP estricto, HSTS 1y, X-Frame-Options DENY, nosniff |
| Rate limiting | 5 capas: IP, email, global, endpoint, gateway DDoS |
| Auth | JWT HttpOnly cookie + 2FA TOTP + lockout progresivo + Argon2id |
| Mass-assignment | 17 modelos endurecidos con denylist explícita |
| File upload | MIME real validation (finfo), tipos restringidos, nombres aleatorios |
| CORS | Lista explícita en prod, fail-fast si mal configurado |
| PII Protection | Sentry beforeSend redacta email/RFC/CURP/CLABE (LFPDPPP) |
| CI/CD | Semgrep OWASP Top 10, Gitleaks, Dependabot, npm/composer audit |
| Monitoreo | /api/health protegido con shared secret timing-safe |
| Supply chain | SBOM (CycloneDX + SPDX) por release |
| Incident response | Runbook completo + plan rotación secretos |

Ver detalles en [`security/SECURITY.md`](security/SECURITY.md) y [`security/incident-response.md`](security/incident-response.md).

---

## 📋 Convenciones del código

### Backend (Laravel)
- `PascalCase` clases, models, enums
- `camelCase` métodos, variables
- `snake_case` columnas DB y JSON keys
- `updateOrCreate` en seeders (idempotencia)
- Middleware por grupo de rutas (Laravel 11, NO en constructor)
- Configuración centralizada en `config/comecyt.php` + `app/Enums/Message.php`

### Frontend (Next.js)
- `PascalCase` componentes, types
- `camelCase` variables, props
- `snake_case` campos heredados del API
- `Array.isArray()` obligatorio antes de `.map()`/`.filter()`
- `useEffect` con función `init` async interna
- Tipos explícitos: `useState<T[]>([])`, nunca `useState([])`

### Git
- [Conventional Commits](https://www.conventionalcommits.org/)
- Tipos: `feat`, `fix`, `sec`, `chore`, `docs`, `refactor`, `test`
- Pre-commit hooks: ESLint + Pint
- PRs pequeños (< 500 líneas idealmente)

Ver detalles en [`DEVELOPMENT.md`](DEVELOPMENT.md) y [`CONTRIBUTING.md`](CONTRIBUTING.md).

---

## 🚨 Lecciones aprendidas

Errores documentados con causa + síntoma + fix viven en **[`../CLAUDE.md`](../CLAUDE.md)** (raíz del repo). Casos cubiertos:

- E1: `apiResource` con plurales españoles irregulares
- E2: Cache + Eloquent → siempre `.toArray()`
- E3: Storage disk explícito
- E4: `EvaluadorController` duplicado
- E5: `UsuariosPruebaSeeder` en producción
- E6: Middleware en constructores (Laravel 11)
- E7: Hydration mismatch (Next.js SSR + cookies)
- E8: Alias de ruta evaluador eliminado erróneamente
- E9: `route('password.reset')` no existe en Laravel API-only
- E10: `User::evaluador()` apuntaba a modelo eliminado
- E11: Estado `'en_revision'` inexistente
- E12: `CarouselSlideSeeder` no estaba conectado a DatabaseSeeder
- E13: Propiedad duplicada `tipo_programa` en types/api.ts
- E14: CORS + `withCredentials` error silencioso
- E15: Scheduler sin cron en producción
- E16: Paginación: `data` cambia de array a objeto

Ver detalle de cada uno en `CLAUDE.md` sección "Errores Documentados".

---

## 🆘 Soporte y contacto

- **Bugs / features**: [Issues del repo](https://github.com/comecyt0/Desarrollo-Tecnol-gico/issues)
- **Vulnerabilidades de seguridad**: Ver [`security/SECURITY.md`](security/SECURITY.md) (NO uses Issues públicos)
- **Datos personales / LFPDPPP**: DPO institucional
- **Soporte general**: `soporte@comecyt.gob.mx`

---

## 📅 Versión actual

**8.1.0** — 2026-06-14 — Cierre operativo: CI/CD + LICENSE + páginas legales + DX completo. Ver [`CHANGELOG.md`](CHANGELOG.md).

---

> **Última actualización del índice:** 2026-06-12
> Si vas a agregar un archivo nuevo a `docs/`, agrégalo también a este índice.
