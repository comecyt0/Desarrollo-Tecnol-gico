# 🤝 Cómo contribuir — COMECYT

¡Gracias por interesarte en mejorar el sistema! Esta guía explica el flujo para enviar cambios al repo.

---

## 1. Antes de empezar

1. Lee [`DEVELOPMENT.md`](DEVELOPMENT.md) y monta el entorno local.
2. Revisa los issues abiertos por si tu idea ya está en discusión.
3. Para cambios grandes (refactors, nuevas features), **abre un issue primero** para alinear con el equipo.

---

## 2. Flujo estándar de PR

```bash
# 1. Fork (si no eres colaborador directo) y clone
git clone https://github.com/<tu-usuario>/Desarrollo-Tecnol-gico.git
cd Desarrollo-Tecnol-gico

# 2. Sync con main
git checkout main
git pull upstream main      # o `origin main` si eres colaborador directo

# 3. Branch nuevo
git checkout -b feat/mi-funcionalidad
# Naming: feat|fix|sec|chore|docs|refactor|test|style|perf / descripción-corta

# 4. Hacer cambios
# ... edits ...

# 5. Tests
cd apps/api && php artisan test
cd apps/web && npx vitest run && npm run lint

# 6. Commit (Conventional Commits)
git add archivos/modificados
git commit -m "feat(api): nueva ruta GET /admin/X para Y"

# 7. Push
git push -u origin feat/mi-funcionalidad

# 8. Crear PR
gh pr create --title "feat(api): nueva ruta GET /admin/X para Y" --body "..."
# o vía UI de GitHub
```

---

## 3. Reglas de los commits

### Formato: Conventional Commits

```
tipo(scope): descripción breve en imperativo

[cuerpo opcional con más detalle]

[footer opcional]
```

### Tipos válidos

| Tipo | Cuándo |
|---|---|
| `feat` | Nueva funcionalidad para el usuario |
| `fix` | Bug fix |
| `sec` | Mejora de seguridad |
| `chore` | Tareas de mantenimiento (bump deps, configs) |
| `docs` | Solo cambios en documentación |
| `refactor` | Refactor sin cambios funcionales |
| `test` | Agregar/corregir tests |
| `style` | Formateo, sin cambios de lógica |
| `perf` | Mejora de performance |

### Scopes comunes

`api`, `web`, `db`, `ci`, `deps`, `auth`, `admin`, `solicitante`, `revisor`, `evaluador`, `convocatoria`, `solicitud`

### Ejemplos buenos

```
feat(api): endpoint /admin/solicitudes/{id}/full con eager-loading completo
fix(web): SolicitudCard rompía al recibir empresa null
sec(api): rate-limit por email en /auth/login (lockout 15min)
chore(deps): bump axios 1.13 → 1.15 (CVE-2025-12345)
docs(api): agregar troubleshooting de WebSocket
```

### Ejemplos malos

```
update                         # ❌ sin contexto
fixed bug                      # ❌ tipo en pasado, sin scope
WIP                            # ❌ commitear WIP solo en branch propio
adds new feature for admin     # ❌ sin Conventional Commits
```

---

## 4. Reglas del PR

### Título

Mismo formato que el commit. **Imperativo, claro.**

### Descripción (body)

```markdown
## Qué cambia
- Lista breve de los cambios principales

## Por qué
Una o dos frases explicando la motivación.

## Cómo probarlo
1. Pasos para verificar el cambio
2. Comandos a ejecutar
3. URLs a visitar

## Captura (si aplica UI)
[screenshot]

## Issues relacionados
Closes #123, Refs #456
```

### Tamaño

- **PRs pequeños son mejores PRs.** Si tu PR cambia > 500 líneas, considera dividirlo.
- **Un PR = una idea**. No mezcles `feat + refactor + fix` en el mismo PR.

### Tests

- Si agregas código nuevo, **agrega tests**.
- Si arreglas un bug, **agrega un test de regresión** que falla antes y pasa después.
- Si quitaste un feature, **quita sus tests**.

### Documentación

- Si cambias el API, actualiza [`docs/API.md`](API.md)
- Si cambias el schema, actualiza [`docs/DATABASE.md`](DATABASE.md) + migración
- Si cambias arquitectura, actualiza [`docs/ARCHITECTURE.md`](ARCHITECTURE.md)
- Si cambias el flujo de usuario, actualiza [`docs/USER_GUIDE.md`](USER_GUIDE.md)

---

## 5. Code review

### Como autor del PR

- Espera la review antes de mergear (incluso si CI pasa).
- Responde a TODOS los comentarios (resuelto, replicar, o "wontfix con razón").
- Si el reviewer pide cambios, hazlos en commits adicionales (no rebase encima) hasta que apruebe.
- Tras aprobación: **squash & merge** (un solo commit en main por PR).

### Como reviewer

- Asume buena fe del autor.
- Sé específico: en vez de "esto está mal", "esto puede romper el flujo X porque Y".
- Aprueba si está suficientemente bueno (no exijas perfección).
- Si bloqueas el PR, deja claro qué se necesita.

---

## 6. CI checks

Tu PR debe pasar los siguientes workflows antes del merge:

| Workflow | Qué corre |
|---|---|
| `security-sast.yml` — semgrep | OWASP Top 10 + p/security-audit + p/php + p/typescript |
| `security-sast.yml` — gitleaks | Secret scanning del histórico |
| `security-sast.yml` — composer-audit | CVE en deps PHP |
| `security-sast.yml` — npm-audit | CVE en deps Node (high+) |
| `security-sast.yml` — eslint-security | ESLint con plugin security |

Si algo falla:
- Mira el log del workflow en la pestaña "Checks" del PR
- Arregla
- Push de nuevo (el workflow re-corre automáticamente)

---

## 7. Tipos de cambios y sus consideraciones

### Cambios de schema (migraciones)

- ⚠️ **Una migración nueva, NUNCA modificar una existente que ya corrió en prod.**
- Si necesitas cambiar el schema, agrega otra migración que haga el cambio.
- `down()` debe revertir cleanamente `up()` (testar en local).

### Cambios de API

- Si quitas/modificas un endpoint usado, **bumpea major version** y documenta deprecation.
- Si agregas un endpoint nuevo, asegúrate que tenga `auth + middleware de rol` correctos.

### Cambios de seguridad

- Si descubres una vulnerabilidad seria, **NO la reportes públicamente**. Sigue [`SECURITY.md`](security/SECURITY.md).
- Si tu PR es un fix de seguridad, prefija con `sec:` y describe el impacto en el body del PR de forma genérica (sin dar PoC público).

### Cambios de UI

- Verifica que se vea bien en:
  - Chrome, Safari, Firefox
  - Desktop + móvil (responsive)
  - Light + Dark mode
- Si es admin, verifica con todos los roles
- Mide accesibilidad: pasa `npx vitest run` (incluye axe-core)

### Cambios de deps

- Verifica que el `package-lock.json` / `composer.lock` esté commiteado
- Si la dep es grande, justifica en el body del PR por qué la necesitamos

---

## 8. Convenciones de código

Ver [`DEVELOPMENT.md §4`](DEVELOPMENT.md#4-estructura-de-código-convenciones) para reglas detalladas.

Resumen:
- **Backend**: PascalCase classes, camelCase methods, snake_case DB
- **Frontend**: PascalCase components, camelCase variables, `Array.isArray()` antes de `.map()`
- **Git**: Conventional Commits, branches nombradas por tipo

---

## 9. Áreas que necesitan ayuda

¿Quieres contribuir pero no sabes por dónde? Estas son áreas con backlog:

- [ ] Tests E2E con Playwright (cubrir los 4 roles)
- [ ] Aumentar coverage del backend al 70%
- [ ] Tema de impresión para PDFs (convenios, dictámenes)
- [ ] PWA: mejorar offline support
- [ ] i18n: completar traducciones al inglés
- [ ] Optimización de queries en `/admin/stats`
- [ ] Migración de JWT HS256 → RS256 con HSM

---

## 10. Código de conducta

### Sé respetuoso

- Diferencias de opinión OK. Personalismos NO.
- Reviews son sobre el código, no sobre la persona.

### Reconoce el trabajo

- Si tu PR resuelve un issue de otra persona, menciónala (`Co-Authored-By:` en commit).
- Si reusas código de otro lado, da crédito (link al origen + check de licencia).

### Privacidad

- ⚠️ **No commitear datos personales reales** ni en tests, ni en fixtures, ni en seeders.
- Usa Faker para datos sintéticos.
- Si necesitas un caso real para un bug, anonimízalo.

---

## 11. Licencia

Al contribuir aceptas que tu código se licencia bajo los términos del proyecto (ver [`LICENSE`](../LICENSE) si existe; si no, asumir copyright institucional COMECYT).

---

## 12. Contacto

- **Bugs / features:** [Issues del repo](https://github.com/comecyt0/Desarrollo-Tecnol-gico/issues)
- **Discusión técnica:** Discord/Slack interno del equipo
- **Seguridad:** Ver [`security/SECURITY.md`](security/SECURITY.md)
- **Privado:** soporte@comecyt.gob.mx

---

¡Gracias por contribuir! 🎉
