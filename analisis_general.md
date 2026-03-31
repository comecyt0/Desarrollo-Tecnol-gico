# Análisis General del Sistema COMECYT - 25/03/2026

## 1. Problemas Identificados y Solucionados

### 1.1 Error HTTP 422 (Unprocessable Entity) al Guardar Borrador
- **Causa:** Discrepancia entre los nombres de campos en el frontend (Next.js) y la API (Laravel).
- **Detalle:** El frontend enviaba `descripcion` y `monto_solicitado`, pero la API esperaba `resumen` y no validaba ni guardaba el monto solicitado.
- **Acción Senior:** Se actualizó el controlador `App\Http\Controllers\Solicitudes\SolicitudController@store` para aceptar el payload real del frontend y persistir el monto de apoyo.

### 1.2 Recuperación del Servidor
- **Causa:** El servidor se encontraba abajo debido a errores en la configuración del `api` guard de Laravel 11.
- **Acción:** Se reiniciaron los servicios de backend (php artisan serve) y frontend (npm run dev). Se verificó que `config/auth.php` tuviera definido el driver `jwt` correctamente.

### 1.3 Eliminación de Datos Estáticos (Hardcoded)
- **Mejora:** Se detectó que la lista de **Modalidades** estaba fija en el frontend.
- **Acción Senior:** 
  - Se creó la tabla `modalidades` en la base de datos vía migración.
  - Se pobló con los valores reales del negocio.
  - Se actualizó el `CatalogoController` para servir estos datos.
  - Se refactorizó la página de **Nueva Solicitud** para consumir la lista dinámicamente. Toda la información ahora proviene de la base de datos PostgreSQL.

### 1.4 Validación de Lista Negra y Convocatoria
- **Mejora:** El controlador activo no estaba validando si la institución del solicitante estaba bloqueada.
- **Acción:** Se implementó la validación obligatoria contra la tabla `lista_negra` y se aseguró que solo se puedan crear solicitudes para convocatorias en estado `activa`.

## 2. Cambios Críticos en la Estructura

| Archivo | Cambio Realizado | Motivo |
|---|---|---|
| `SolicitudController.php` | Portabilidad de reglas y corregido mapeado de campos | Solucionar Error 422 y mejorar seguridad |
| `Solicitud.php` (Modelo) | Adición de `accessor` para `resumen` | Compatibilidad con la vista de detalles del frontend |
| `CatalogoController.php` | Inclusión del catálogo `modalidades` | Cumplir requisito de cero datos estáticos |
| `nueva/page.tsx` | Eliminado SelectItems estáticos; implementada carga fetch | Dinamismo y cumplimiento de reglas de negocio |

---
**Nota:** Se identificó un segundo controlador `SolicitudController` en la raíz de `app/Http/Controllers`. Se recomienda **no usarlo** ya que las rutas de `api.php` apuntan al controlador dentro del namespace `Solicitudes`.
