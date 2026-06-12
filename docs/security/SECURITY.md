# 🔒 Política de Seguridad — COMECYT

> Cómo reportar vulnerabilidades + compromisos de seguridad del proyecto

---

## 1. Versiones soportadas

| Versión | Soporte de seguridad |
|---|---|
| 8.x (actual) | ✅ Activa |
| 7.x | ⚠️ Solo críticas hasta 2026-12-31 |
| < 7.x | ❌ No soportada |

---

## 2. Reportar una vulnerabilidad

### NO uses GitHub Issues públicos para reportes de seguridad

Issues públicos exponen el bug antes de que tengamos chance de parchearlo, dando ventana a explotación.

### En su lugar:

| Tipo | A dónde reportar |
|---|---|
| Vulnerabilidad explotable activa | `seguridad@comecyt.gob.mx` (PGP key: ver §3) |
| Bug de seguridad sin PoC | GitHub Security Advisories (vista privada) |
| Sospecha de compromiso en producción | Llamada al líder TIC + email |

### Qué incluir en tu reporte

```
Asunto: [SEGURIDAD] Breve descripción del problema

Hola,

Descubrí una posible vulnerabilidad en COMECYT.

Tipo: (auth bypass / SQL injection / XSS / SSRF / etc.)
Severidad estimada: (CVSS si lo conoces)
Componente afectado: (apps/api, apps/web, dependencia, infra)
Versión donde lo probé: (commit SHA o tag)

Descripción:
[explica el problema técnicamente]

Reproducción (PoC):
[pasos concretos para reproducir; HTTP request si aplica]

Impacto:
[qué puede hacer un atacante si lo explota]

Mitigación sugerida (opcional):
[si tienes una idea de cómo arreglarlo]

Mi info de contacto:
- Email: ...
- (Opcional) PGP key: ...
```

### Tiempo de respuesta esperado

- **Acuse de recibo**: ≤ 48h
- **Confirmación de la vulnerabilidad**: ≤ 7 días
- **Fix desplegado**: depende de severidad
  - 🟥 Crítica/Alta (auth bypass, RCE, data exfil): ≤ 7 días
  - 🟧 Media (IDOR limitado, XSS reflejado): ≤ 30 días
  - 🟨 Baja (info disclosure menor): ≤ 90 días

### Disclosure coordinado

Esperamos que NO publiques la vulnerabilidad hasta:
- 90 días después del reporte, **O**
- El fix esté desplegado en producción, **O**
- Acuerdo mutuo de fecha de publicación

A cambio:
- Crédito en el `CHANGELOG.md` (a menos que pidas anonimato)
- Mención en hall-of-fame de seguridad (si te interesa)

---

## 3. PGP key (próximamente)

Pendiente de generación por parte del equipo institucional COMECYT. Mientras tanto, usa el email regular.

```
Email: seguridad@comecyt.gob.mx
Fingerprint: TBD
```

---

## 4. Scope (qué SÍ aplica)

### En scope (reporta estos)

- Vulnerabilidades en el código de `apps/api` y `apps/web`
- Vulnerabilidades en deploy scripts (`deploy.sh`, `DeployCheck.php`)
- Vulnerabilidades en CI workflows
- Configuración insegura en archivos commiteados (`.env.example`, `nginx-comecyt.conf`)
- Vulnerabilidades en dependencias directas que afecten la app

### Fuera de scope (no reportes estos)

- Vulnerabilidades en dependencias transitivas sin impacto en la app (reporta upstream)
- Bugs de UX/UI sin impacto de seguridad
- Auto-XSS (te lo haces a ti mismo en tu propia consola)
- Reportes generados por scanners automatizados sin verificación manual
- Vulnerabilidades teóricas sin PoC
- Phishing/social engineering contra usuarios del COMECYT (no es responsabilidad del código)
- DoS volumétrico (mitigar con infra CDN/WAF)

---

## 5. Vulnerabilidades comunes a verificar (para curious)

Si quieres hacer una auditoría informal, verifica:

### OWASP Top 10 — Web

- [ ] A01: Broken Access Control (IDOR, BFLA, BOPLA)
- [ ] A02: Cryptographic Failures
- [ ] A03: Injection (SQL, NoSQL, command, LDAP)
- [ ] A04: Insecure Design
- [ ] A05: Security Misconfiguration
- [ ] A06: Vulnerable Components (CVE en deps)
- [ ] A07: Identification & Authentication Failures
- [ ] A08: Software & Data Integrity Failures (deserialización, supply chain)
- [ ] A09: Security Logging & Monitoring Failures
- [ ] A10: SSRF

### OWASP API Security Top 10

- [ ] API1: BOLA (chequeo de ownership)
- [ ] API2: Broken Authentication
- [ ] API3: BOPLA (sensitive data en responses)
- [ ] API4: Unrestricted Resource Consumption (rate limit)
- [ ] API5: BFLA (admin endpoints sin guard)
- [ ] API6: Unrestricted Access to Sensitive Business Flows
- [ ] API7: SSRF
- [ ] API8: Misconfiguration
- [ ] API9: Improper Inventory Management
- [ ] API10: Unsafe Consumption of APIs

### Específicos del dominio mexicano

- [ ] Cumplimiento LFPDPPP (datos personales)
- [ ] Validación de RFC, CURP, CLABE
- [ ] Aviso de privacidad expuesto

---

## 6. Controles de seguridad implementados

Lista de defensas activas (post-auditoría 2026-06-12):

| Control | Implementación |
|---|---|
| JWT en cookie HttpOnly | `AuthController::respondWithToken()` |
| 2FA TOTP opcional | `Google2FA` |
| Rate limiting 5 capas | `AuthLoginRate`, `RateLimit`, `ApiGateway`, `CircuitBreaker` |
| Argon2id hashing | `HASH_DRIVER=argon2id` en `.env` |
| Mass-assignment denylist | 17 modelos endurecidos |
| CSRF: stateless JWT | Sin sesiones cookie-based |
| CORS estricto en prod | `config/cors.php` con guard de `localhost` |
| Security headers | `SecurityHeadersMiddleware` + `next.config.ts` |
| CSP estricto | `next.config.ts` con `frame-ancestors 'none'` |
| File upload MIME real | `ValidatesBinaryMimeTypes` trait con `finfo_file()` |
| File upload nombres random | `Str::random(40)` |
| SSRF allowlist | `CarouselController::imageUrlRules()` |
| Sentry PII scrubbing | `beforeSend` redacta email/RFC/CURP/CLABE/JWT |
| Push protection en repo | GitHub Secret Scanning activo |
| Dependabot | Semanal, agrupado por ecosystem |
| SAST en CI | Semgrep + Gitleaks + npm/composer audit |
| SBOM por release | CycloneDX + SPDX |
| Runbook IR | `docs/security/incident-response.md` |
| Plan de rotación de secretos | En runbook IR §8 |
| Health endpoint protegido | Shared secret timing-safe |
| `DeployCheck` con 14 validaciones | `app:deploy-check` |

---

## 7. Backlog de seguridad (mejoras planeadas)

| Mejora | Prioridad | Cuándo |
|---|---|---|
| Migrar JWT a RS256 con HSM | 🟡 Media | Cuando TIC entregue HSM institucional |
| Implementar mTLS para servicios internos | 🟢 Baja | Si migramos a microservicios |
| Penetration test externo formal | 🟡 Media | Anual |
| WAF perimetral (Cloudflare/Imperva) | 🟡 Media | Próximo trimestre |
| Auditoría de código continua (CodeQL) | 🟢 Baja | Q3 2026 |
| Backup off-site cifrado (S3 Object Lock) | 🔴 Alta | Próximo mes |

---

## 8. Compromisos del equipo

- ✅ NO usaremos credenciales por defecto en producción
- ✅ NO commiteamos secretos al repo (Gitleaks en CI los detecta)
- ✅ Rotamos secretos según calendario (`docs/security/incident-response.md` §8)
- ✅ Aplicamos parches de seguridad ≤ 7 días tras divulgación pública
- ✅ Auditoría de logs de seguridad ≥ 1 vez por semana
- ✅ Tests de integración cubren los flujos de auth/autz críticos
- ✅ Aviso de privacidad LFPDPPP disponible en `/privacidad`

---

## 9. Referencias

- [LFPDPPP](http://www.diputados.gob.mx/LeyesBiblio/pdf/LFPDPPP.pdf) (Ley Federal de Protección de Datos Personales)
- [LGPDPPSO](http://www.diputados.gob.mx/LeyesBiblio/pdf/LGPDPPSO.pdf) (sujetos obligados)
- [OWASP ASVS 4.0.3](https://owasp.org/www-project-application-security-verification-standard/)
- [NIST SP 800-218](https://csrc.nist.gov/Projects/ssdf) (SSDF)
- [`docs/security/incident-response.md`](incident-response.md) — runbook IR
- Skill interna: `ciberseguridad-auditor-integral v2.0`

---

> **Última actualización:** 2026-06-12
> **Próxima revisión obligatoria:** 2026-12-12 (semestral)
