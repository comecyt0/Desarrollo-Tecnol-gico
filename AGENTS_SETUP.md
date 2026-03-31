# 🤖 Agents, Skills y Commands - Acceso Rápido

**Integración completada:** 30 de marzo de 2026

---

## 🚀 Inicio Rápido

Tienes acceso a profesional toolkit de desarrollo:

- **1 Agent** → Code Reviewer (validación post-implementación)
- **3 Commands** → Planificación y ejecución
- **25+ Skills** → Desarrollo, testing, seguridad, UI/UX

### Cómo usar

Simplemente menciona qué necesitas y Claude invocará automáticamente los elementos apropiados:

```
Usuario: "Implementa el sistema de notificaciones con TDD"
Claude → Invoca: tdd-workflow + backend-patterns + e2e-testing
```

---

## 📖 Documentación

- **[_agents/README.md](_agents/README.md)** ← Guía de inicio
- **[_agents/AGENTS_SKILLS_INDEX.md](_agents/AGENTS_SKILLS_INDEX.md)** ← Índice completo
- **[_agents/INTEGRATION_SUMMARY.txt](_agents/INTEGRATION_SUMMARY.txt)** ← Resumen ejecutivo

---

## 📁 Estructura

```
_agents/
├── agents/          → code-reviewer (revisión de código)
├── commands/        → brainstorm, write-plan, execute-plan
├── skills/          → 25+ skills de desarrollo
└── workflows/       → (futuro) workflows automatizados
```

---

## 🎯 Skills Principales por Caso de Uso

| Necesito... | Usa estos skills | Comando |
|-------------|-----------------|---------|
| Planificar una feature | brainstorm, write-plan, execute-plan | `/brainstorm` |
| Implementar backend | backend-patterns, api-design | Menciona la feature |
| Implementar frontend | frontend-patterns, design-system | Menciona la feature |
| Escribir tests primero | tdd-workflow, e2e-testing | `/tdd-workflow` |
| Revisar seguridad | security-review | Menciona audit |
| Revisar código | code-reviewer agent | Menciona "código listo" |
| Diseñar UI | design, ui-ux-pro-max | Menciona mockup |

---

## ⚡ Próximos Pasos

1. Lee **_agents/README.md** (5 min)
2. Explora **_agents/AGENTS_SKILLS_INDEX.md** para lista completa
3. Menciona qué necesitas implementar
4. Claude invoca los skills apropiados automáticamente

---

**¡Listo para desarrollar!** 🚀
