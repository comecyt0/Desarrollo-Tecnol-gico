# 👥 Guía de Usuario por Rol — COMECYT

> **Audiencia:** Usuarios finales del sistema (admins, revisores, evaluadores, solicitantes)
> **Para soporte:** soporte@comecyt.gob.mx
> **Última revisión:** 2026-06-12

---

## Tabla de contenido

1. [Acceso al sistema y primer login](#1-acceso-al-sistema-y-primer-login)
2. [Rol: Solicitante (Empresa)](#2-rol-solicitante-empresa)
3. [Rol: Revisor Documental](#3-rol-revisor-documental)
4. [Rol: Evaluador Técnico](#4-rol-evaluador-técnico)
5. [Rol: Administrador](#5-rol-administrador)
6. [Notificaciones y configuración personal](#6-notificaciones-y-configuración-personal)
7. [Preguntas frecuentes](#7-preguntas-frecuentes)

---

## 1. Acceso al sistema y primer login

### 1.1 ¿Cómo solicito acceso?

Si eres una **empresa o institución** que quiere postular a una convocatoria:

1. Visita `https://dominio.gob.mx/solicitar-acceso`
2. Completa el formulario de 5 pasos:
   - **Paso 1 — Cuenta**: nombre, email, contraseña
   - **Paso 2 — Empresa**: nombre, RFC, tipo de persona (física/moral/asociación), rol de supervisión
   - **Paso 3 — Contactos**: 4 contactos (Responsable es obligatorio; Legal, Administrativo, Técnico son opcionales)
   - **Paso 4 — Detalles**: cargo, teléfono, justificación
   - **Paso 5 — Términos**: aceptar Términos y Condiciones
3. Envía
4. Espera el correo de aprobación del administrador (en ~24-48h hábiles)

Si eres **revisor o evaluador**: el administrador del COMECYT crea tu cuenta directamente y te llega un correo con el link para establecer contraseña.

### 1.2 Login

1. `https://dominio.gob.mx/login`
2. Ingresa tu email institucional + contraseña
3. Si tienes **2FA activado**, ingresa el código TOTP de tu app autenticadora (Google Authenticator, Authy, 1Password, etc.)

### 1.3 Olvidé mi contraseña

Por seguridad institucional, los resets de contraseña requieren aprobación del admin:

1. Click en "¿Olvidaste tu contraseña?" en login
2. Ingresa tu email
3. El sistema notifica al admin con tu solicitud
4. El admin aprueba (manual o automático según política)
5. Recibes email con link para crear nueva contraseña (válido 1h)

> ⚠️ Este flujo NO es el reset directo de Laravel — es deliberado para evitar account takeover.

### 1.4 Activar 2FA (recomendado para admins)

1. Login → menú perfil → **Seguridad**
2. **Activar autenticación de dos factores**
3. Escanea el QR con tu app TOTP
4. Ingresa 6 dígitos para confirmar
5. **Guarda los códigos de recuperación** en lugar seguro (te servirán si pierdes tu teléfono)

---

## 2. Rol: Solicitante (Empresa)

### 2.1 Vista inicial

Tras login verás `/solicitante/dashboard` con:
- Convocatorias activas (puedes postular)
- Mis solicitudes en curso
- Notificaciones recientes

### 2.2 Crear una solicitud

1. **Convocatorias activas** → click en una para ver detalles
2. **Postular** → se crea una solicitud en estado `borrador`
3. Completa el formulario (depende de los campos dinámicos de la convocatoria):
   - Información del proyecto (título, descripción, objetivos)
   - Cronograma
   - Presupuesto (rubros)
   - Equipo de trabajo
4. Sube los documentos requeridos (PDF, máx 5MB c/u)
5. **Guardar borrador** las veces que necesites
6. **Enviar** cuando esté listo → estado pasa a `enviada` (ya no editable sin admin)

### 2.3 Estados de mi solicitud

| Estado | Qué significa | Qué puedes hacer |
|---|---|---|
| `borrador` | En edición | Modificar, eliminar |
| `enviada` | Revisor la tiene | Esperar |
| `observada` | Revisor pidió correcciones | **Corregir y reenviar** |
| `en_evaluacion` | Evaluador la tiene | Esperar dictamen |
| `aprobada` | Pasó evaluación | Esperar convenio |
| `rechazada` | No aprobada | Ver motivo, archivada |
| `cancelada` | Cancelada por ti o admin | Archivada |
| `convenio` | Firmar convenio | Descargar y firmar |
| `ministracion` | Captura datos bancarios | **Llenar CLABE, banco, titular** |
| `seguimiento` | Pago realizado, en ejecución | Subir informe al final |
| `cerrada` | Proyecto concluido | Solo lectura |

### 2.4 Atender observaciones del revisor

Si recibes email "Tu solicitud tiene observaciones":

1. `/solicitante/solicitudes/{id}` → ver comentarios del revisor
2. Editar los campos señalados
3. Reemplazar documentos si es necesario
4. **Reenviar** → vuelve a `enviada`

### 2.5 Firmar convenio (estado `convenio`)

1. Descarga el PDF del convenio generado por el sistema
2. Imprime, firma autógrafa por el representante legal
3. Sube el PDF firmado a la solicitud
4. El admin lo valida y pasa el estado a `ministracion`

### 2.6 Capturar datos bancarios (estado `ministracion`)

1. `/solicitante/ministraciones/{id}`
2. Llenar:
   - Banco (selecciona del catálogo)
   - CLABE interbancaria (18 dígitos)
   - Número de cuenta
   - Titular de la cuenta (debe coincidir con la empresa)
3. Subir documentos:
   - Carta compromiso firmada
   - Carátula del estado de cuenta bancario
   - Constancia de situación fiscal (SAT)
   - Factura institucional (si aplica)
4. Enviar para revisión

### 2.7 Entregar informe final (estado `seguimiento`)

Al concluir el plazo (típicamente 20 días hábiles tras el evento):

1. `/solicitante/solicitudes/{id}/informe`
2. Subir PDF del informe final
3. Capturar resultados obtenidos
4. Adjuntar evidencias (fotos, certificados, listas de asistencia)
5. Enviar → revisor lo valida → estado pasa a `cerrada`

> ⚠️ Si NO entregas el informe en plazo, tu empresa puede ser sancionada (Lista Negra).

---

## 3. Rol: Revisor Documental

### 3.1 Dashboard

`/revisor/dashboard` muestra:
- Solicitudes **pendientes** (estados `enviada` + `observada`)
- Solicitudes **completadas** (aprobaste a evaluación)
- KPIs personales (tiempo promedio de revisión)

### 3.2 Revisar una solicitud

1. `/revisor/solicitudes` → click en una pendiente
2. Revisa cada documento subido (descargas inline)
3. Verifica:
   - Datos de la empresa son válidos
   - Documentación completa según la convocatoria
   - No hay errores formales (campos vacíos, montos absurdos, etc.)

### 3.3 Acciones del revisor

| Acción | Cuándo | Efecto |
|---|---|---|
| **Aprobar** | Documentación está bien | `enviada → en_evaluacion` (pasa a evaluador) |
| **Observar** | Faltan o están mal docs/datos | `enviada → observada` (vuelve al solicitante) |
| **Devolver con motivos** | Múltiples errores | igual que Observar, con texto detallado |

### 3.4 Aprobar informe final

Cuando un solicitante sube el informe final (estado `seguimiento`):

1. `/revisor/informes` → lista de informes pendientes
2. Click → verificar PDF + resultados + evidencias
3. **Aprobar** → estado de solicitud pasa a `cerrada`
4. Si insuficiente: **Rechazar** con observaciones (solicitante debe corregir)

---

## 4. Rol: Evaluador Técnico

### 4.1 Asignaciones

`/evaluador/asignaciones` muestra las solicitudes que el admin te asignó.

Estado de tu asignación:
- 🟠 `asignado` — no has abierto la rúbrica
- 🟡 `evaluando` — abriste, en progreso
- 🟢 `concluido` — emitiste dictamen

### 4.2 Evaluar una solicitud

1. Click en una asignación `asignado`
2. **Leer el proyecto completo** (descripción, objetivos, cronograma, presupuesto)
3. **Iniciar evaluación** → estado pasa a `evaluando` (compromiso)
4. Llenar la rúbrica:
   - Cada criterio tiene una ponderación (ej: Calidad técnica 30%, Impacto 25%, etc.)
   - Califica cada criterio del 0 al 10
   - El sistema calcula el puntaje final ponderado
5. Justifica con comentarios cada calificación
6. Emite dictamen:
   - **Recomendado** (≥ umbral de aprobación)
   - **No recomendado** (< umbral)
7. **Guardar dictamen** → estado pasa a `concluido`

### 4.3 Carta de imparcialidad

Antes de iniciar evaluación, debes aceptar la carta de imparcialidad confirmando que:
- No tienes conflicto de interés con la empresa
- No tienes relación personal/financiera con los responsables del proyecto
- Mantendrás confidencialidad

Si tienes conflicto: rechaza la asignación y comunica al admin para reasignar.

### 4.4 Histórico

`/evaluador/historico` lista todas tus evaluaciones concluidas, con:
- Folio, empresa, fecha
- Tu calificación final
- Decisión final del comité (puede diferir de tu dictamen)

---

## 5. Rol: Administrador

### 5.1 Panel general

`/admin/dashboard` muestra:
- Solicitudes activas por estado
- Convocatorias próximas a cerrar
- Alertas recientes (logins anómalos, ministraciones pendientes, etc.)

### 5.2 Operaciones más comunes

#### Aprobar accesos nuevos
`/admin/solicitudes-acceso` → revisar → aprobar/rechazar.

#### Aprobar resets de contraseña
`/admin/reset-requests` → ver email + último login → aprobar (envía link al usuario).

#### Crear/editar convocatorias
`/admin/convocatorias/nueva` — wizard de 7 pasos:
1. Información básica (nombre, fechas, montos)
2. Tipo de programa
3. Campos dinámicos del formulario
4. Documentos requeridos
5. Rubros de presupuesto
6. Criterios de evaluación (rúbrica)
7. Revisión y publicación

Mientras llenas el wizard, hay un **panel lateral en vivo** que muestra cómo verá el solicitante la convocatoria.

#### Gestionar empresas
`/admin/empresas` — CRUD + ver historial de solicitudes por empresa.

#### Lista Negra
`/admin/lista-negra` — empresas vetadas (no pueden postular).
- Registrar sanción: empresa + motivo + fecha inicio/fin
- Remover veto: cuando la situación se subsana

#### Asignar evaluadores
`/admin/solicitudes/{id}` → "Asignar evaluador":
- Seleccionar evaluador (del catálogo)
- Fecha límite de evaluación
- Notificación automática al evaluador

#### Ver auditoría
`/admin/audit-log` — registro inmutable de:
- Logins (éxito/fallo)
- Cambios de estado de solicitudes
- Asignaciones de evaluadores
- Cambios en lista negra
- Resets de contraseña

### 5.3 Reportes y exportación

`/admin/reportes` permite descargar en Excel:
- Solicitudes por convocatoria
- Ministraciones por periodo
- Empresas activas/sancionadas
- Evaluadores y sus dictámenes

---

## 6. Notificaciones y configuración personal

### 6.1 Notificaciones en tiempo real

La campanita en la barra superior se actualiza al instante (WebSocket) cuando:
- Recibes una asignación
- Cambia el estado de tu solicitud
- Hay una observación que atender
- Se acerca la fecha límite de un informe

Si la pestaña está cerrada, recibes notificación push del navegador (si autorizaste).

### 6.2 Email

Por defecto recibes email para:
- Cambios de estado importantes
- Asignaciones (evaluador)
- Resets de contraseña (admin)
- Recordatorios de fecha límite

Configurable en `/configuracion/notificaciones`.

### 6.3 Idioma

`/configuracion/idioma` — Español (default) o Inglés.

### 6.4 Tema visual

Botón en la barra superior:
- Claro
- Oscuro
- Automático (según sistema operativo)

---

## 7. Preguntas frecuentes

### 7.1 No puedo subir un PDF de 8 MB

El límite es 5 MB por documento. Comprime el PDF con:
- Adobe Acrobat: Archivo → Reducir tamaño
- ilovepdf.com (web, gratuito)
- `gs -sDEVICE=pdfwrite -dPDFSETTINGS=/ebook -o reducido.pdf original.pdf` (línea de comandos)

### 7.2 ¿Puedo editar mi solicitud después de enviarla?

No. Solo si el revisor la regresa con observaciones (estado `observada`). Por eso usa "Guardar borrador" mientras la armas.

### 7.3 ¿Cómo cancelo una solicitud?

Estados que permiten cancelar: `borrador`, `enviada`, `observada`, `en_evaluacion`.
`/solicitante/solicitudes/{id}` → botón "Cancelar" (irreversible).

### 7.4 ¿Cuándo cobro la ministración?

Tras pasar todos los estados: `aprobada → convenio → ministracion → pagada`. Típicamente toma 2-4 semanas desde el dictamen aprobatorio.

### 7.5 Olvidé mi código 2FA y no tengo los códigos de recuperación

Contacta al admin del sistema (`soporte@comecyt.gob.mx`). El admin puede desactivar tu 2FA (queda en log de auditoría). Activa uno nuevo al recuperar acceso.

### 7.6 ¿Por qué no aparece la convocatoria en la lista pública?

La convocatoria debe:
- Estar en estado `publicada`
- Tener `fecha_inicio ≤ hoy ≤ fecha_cierre`
- Tener `tipo_programa_id` configurado (Lote 7+)
- Tener `categoria_id` configurado (Lote 4+)

Si tu empresa está en **Lista Negra** (`activa=true`), tampoco la verás.

### 7.7 La campanita no se actualiza en tiempo real

Verifica que `wss://dominio.gob.mx/app/...` no esté bloqueado por proxy/firewall de tu institución. Si las notificaciones llegan al recargar la página pero no en vivo: el WebSocket está bloqueado, contacta a tu TI corporativo.

### 7.8 ¿Hay app móvil?

El sistema es **PWA** — puedes "Instalar como app" desde Chrome/Safari móvil (menú → "Agregar a pantalla de inicio"). Funciona offline para vista previa de tus solicitudes.

### 7.9 ¿Puedo usar el sistema en Internet Explorer?

No. Navegadores soportados:
- Chrome / Edge ≥ 110
- Safari ≥ 16
- Firefox ≥ 110

### 7.10 ¿Quién ve mis datos?

Según rol:
- **Solicitante**: solo ves tus solicitudes
- **Revisor**: las solicitudes asignadas a tu revisión + comprobantes
- **Evaluador**: solo las solicitudes que te asignaron (sin datos de empresa hasta firmar carta de imparcialidad)
- **Admin**: todo, con auditoría de cada acceso

Conforme a **LFPDPPP**: ver Aviso de Privacidad en `/privacidad`.

---

> Para reportar problemas: `https://github.com/comecyt0/Desarrollo-Tecnol-gico/issues` (si tu rol lo permite) o `soporte@comecyt.gob.mx`.
