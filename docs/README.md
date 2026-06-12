# 📚 Documentación COMECYT

Sistema de Gestión de Proyectos de Desarrollo Tecnológico y Vinculación.

---

## Por dónde empezar

### Si vas a **instalar el sistema en un servidor nuevo**
→ **[DEPLOYMENT.md](DEPLOYMENT.md)** — Guía completa de despliegue (60-90 min)
  - Requisitos del servidor
  - Instalación paso a paso
  - Configuración de nginx + SSL
  - Servicios persistentes (supervisor)
  - Verificación final

### Si vas a **administrar el sistema día a día**
→ **[OPERATIONS.md](OPERATIONS.md)** — Manual de operación
  - Roles y flujos del negocio
  - Comandos artisan más usados
  - Monitoreo en vivo
  - Backups
  - Troubleshooting de problemas comunes
  - Métricas a monitorear

### Si tienes un **incidente de seguridad**
→ **[security/incident-response.md](security/incident-response.md)** — Runbook IR
  - Cadena de mando + contactos
  - Playbooks por tipo de incidente
  - Brecha de datos personales (LFPDPPP)
  - Plan de rotación de secretos

---

## Stack tecnológico (resumen)

| Capa | Tecnología |
|---|---|
| Frontend | Next.js 16 (App Router) + React 19 + TypeScript |
| Backend | Laravel 11 (API-only) |
| Estilos | Tailwind v4 + Shadcn UI |
| Animaciones | Framer Motion |
| Auth | JWT (HS256, HttpOnly cookie) + 2FA TOTP opcional |
| DB | PostgreSQL 18 |
| WebSocket | Laravel Reverb |
| PDF | barryvdh/laravel-dompdf |
| Excel | maatwebsite/laravel-excel |
| Email | SMTP (institucional) |
| Monitoring | Sentry (con PII scrubbing LFPDPPP-safe) |
| CI/CD | GitHub Actions (Semgrep, Gitleaks, Dependabot) |

---

## Arquitectura en una página

```
                    HTTPS
                      │
                      ▼
              ┌──────────────┐
              │    nginx     │  ← reverse-proxy + TLS
              └──────────────┘
              /api/*       /*
                 │            │
                 ▼            ▼
        ┌──────────────┐  ┌──────────────┐
        │ Laravel 11   │  │ Next.js 16   │
        │  (php-fpm)   │  │  (Node 20)   │
        └──────┬───────┘  └──────────────┘
               │
               ▼
        ┌──────────────┐
        │ PostgreSQL 18│
        └──────────────┘
               │
               ▼
        ┌──────────────┐
        │ Reverb (WS)  │  ← Notificaciones push
        └──────────────┘
```

---

## Cuatro roles del sistema

| Rol | ID | Descripción |
|---|---|---|
| Administrador | 1 | Control total |
| Revisor Documental | 2 | Valida documentación de solicitudes |
| Evaluador Técnico | 3 | Puntúa proyectos según rúbrica |
| Solicitante | 4 | Empresa que solicita financiamiento |

---

## Postura de seguridad

Auditoría completa según skill `ciberseguridad-auditor-integral v2.0`:

| Capa | Implementación |
|---|---|
| **Headers HTTP** | CSP estricto, HSTS 1y, X-Frame-Options DENY, nosniff, Permissions-Policy |
| **Rate limiting** | 5 capas: IP, email, global, endpoint-específico, gateway DDoS |
| **Auth** | JWT HttpOnly cookie + 2FA TOTP + lockout progresivo + Argon2id |
| **Mass-assignment** | 17 modelos endurecidos con denylist explícita |
| **File upload** | Validación MIME real (finfo), tipos restringidos, nombres aleatorios |
| **CORS** | Lista explícita en prod, fail-fast si mal configurado |
| **PII Protection** | Sentry beforeSend redacta email/RFC/CURP/CLABE (LFPDPPP) |
| **CI/CD** | Semgrep OWASP Top 10, Gitleaks, Dependabot, npm/composer audit |
| **Monitoreo** | /api/health protegido con shared secret timing-safe |
| **Supply chain** | SBOM (CycloneDX + SPDX) por release |

Ver `security/incident-response.md` para procedimientos operativos.

---

## Convenciones del código

### Backend (Laravel)
- `PascalCase` para clases, models, enums
- `camelCase` para métodos, variables
- `snake_case` para columnas DB y JSON keys
- `updateOrCreate` en seeders (idempotencia)
- Middleware por grupo de rutas (Laravel 11, NO en constructor)
- Configuración centralizada en `config/comecyt.php` + `app/Enums/Message.php`

### Frontend (Next.js)
- `PascalCase` para componentes, types, interfaces
- `camelCase` para variables, props
- `snake_case` para campos heredados del API
- `Array.isArray()` obligatorio antes de `.map()`/`.filter()`
- `useEffect` con función `init` async interna
- Tipos explícitos: `useState<T[]>([])`, nunca `useState([])`

### Git
- Conventional commits: `tipo(scope): descripción`
- Tipos: `feat`, `fix`, `sec`, `chore`, `docs`, `refactor`, `test`
- Pre-commit hooks: ESLint + Pint
- Branch `main` protegida en producción

---

## Errores históricos documentados

Ver el archivo principal **[CLAUDE.md](../CLAUDE.md)** (raíz del repo) — sección "Errores Documentados (No Repetir)". Incluye 16 lecciones aprendidas con causa, síntoma y fix.

---

## Soporte y contacto

- **Issues técnicos**: [Issues del repo](https://github.com/comecyt0/Desarrollo-Tecnol-gico/issues)
- **Seguridad**: privadamente al equipo TIC (no en issues públicos)
- **Datos personales (LFPDPPP)**: DPO institucional

---

> **Última actualización del índice:** 2026-06-12
