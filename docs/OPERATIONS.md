# 🛠️ Manual de Operación — COMECYT

> **Audiencia:** Administradores del sistema, equipo de TIC, soporte L1/L2
> **Pre-requisito:** Sistema ya desplegado (ver `docs/DEPLOYMENT.md`)
> **Actualizado:** 2026-06-12

---

## 1. Cuentas y roles del sistema

| Rol | ID | Capacidades clave |
|---|---|---|
| **Administrador** | 1 | Control total: convocatorias, usuarios, evaluadores, ministraciones, lista negra |
| **Revisor Documental** | 2 | Valida documentación de solicitudes (`enviada → en_evaluacion / observada`) |
| **Evaluador Técnico** | 3 | Puntúa proyectos asignados según rúbrica (`asignado → evaluando → concluido`) |
| **Solicitante (empresa)** | 4 | Crea solicitudes, sube documentos, recibe ministración |

### Admin inicial

Después del primer `db:seed`:
- Email: `admin@comecyt.gob.mx`
- Password: ver el `DatabaseSeeder.php` (debe cambiarse al primer login)

> ⚠️ **Cambia la contraseña del admin INMEDIATAMENTE** después del primer login. La password default está en el código del seeder y es pública.

### Crear nuevos admins manualmente

Desde el panel `/admin/usuarios` → "Crear usuario" → seleccionar rol Administrador.

---

## 2. Flujo de la convocatoria (estados)

```
borrador → enviada → en_evaluacion → aprobada → convenio → ministracion → seguimiento → cerrada
                ↕
            observada (ciclo revisor)
rechazada (desde: enviada | en_evaluacion | aprobada)
cancelada (desde: borrador | enviada | observada | en_evaluacion)
```

**Estados de AsignacionEvaluador:**

```
asignado → evaluando → concluido
```

Detalles:
- `asignado`: admin asignó pero el evaluador no ha abierto la rúbrica
- `evaluando`: el evaluador llamó `PUT /evaluador/asignaciones/{id}/iniciar-evaluacion`
- `concluido`: dictamen emitido con `POST /evaluador/asignaciones/{id}/dictamen`

---

## 3. Comandos artisan más usados

```bash
cd /var/www/comecyt/apps/api

# ─── Salud y validación ───────────────────────────────
sudo -u www-data php artisan app:deploy-check       # 14 checks de prod-readiness
sudo -u www-data php artisan about                  # info general del entorno

# ─── Convocatorias ────────────────────────────────────
sudo -u www-data php artisan convocatorias:close-expired    # cerrar las vencidas (corre auto cada hora)
sudo -u www-data php artisan convocatorias:notificar-cierre --dias=7  # alerta a admins

# ─── Cache ────────────────────────────────────────────
sudo -u www-data php artisan config:cache           # rebuild de config tras editar .env
sudo -u www-data php artisan route:cache            # rebuild de rutas
sudo -u www-data php artisan view:clear             # limpia vistas compiladas
sudo -u www-data php artisan cache:clear            # limpia cache de aplicación

# ─── Rotación de secretos ─────────────────────────────
sudo -u www-data php artisan key:generate --force   # rotar APP_KEY
sudo -u www-data php artisan jwt:secret --force     # rotar JWT_SECRET (invalida todas las sesiones)

# ─── Storage symlink ──────────────────────────────────
sudo -u www-data php artisan storage:link           # /storage → storage/app/public

# ─── Migraciones ──────────────────────────────────────
sudo -u www-data php artisan migrate --force        # aplicar nuevas migraciones
sudo -u www-data php artisan migrate:status         # ver cuáles están aplicadas
# ⚠️ NUNCA correr migrate:fresh en producción (borra datos)
```

---

## 4. Operaciones comunes desde el panel admin

### 4.1 Aprobar una solicitud de acceso (postulante nuevo)

1. Login como admin
2. `/admin/solicitudes-acceso`
3. Click en una solicitud pendiente
4. Revisar datos de empresa + contactos
5. **Aprobar** → genera User + Empresa + envía email con link de password

### 4.2 Reset de contraseña (mediado por admin)

El sistema NO permite reset directo. Flujo:
1. Usuario → `/forgot-password` → ingresa email
2. Backend crea `PasswordResetRequest` (estado: pendiente)
3. Admin → `/admin/reset-requests` → ve la petición
4. Admin → click "Aprobar" → Laravel envía email al usuario con link

> Razón del flujo: COMECYT requiere control institucional sobre resets para evitar account takeover.

### 4.3 Asignar evaluador a una solicitud

1. La solicitud debe estar en estado `en_evaluacion`
2. `/admin/solicitudes/{id}` → "Asignar evaluador"
3. Seleccionar evaluador + fecha límite
4. El evaluador recibe email + notificación push

### 4.4 Generar convenio + ministración

Tras aprobar evaluación:
1. `/admin/solicitudes/{id}/generar-convenio` (genera PDF descargable)
2. Una vez firmado: estado → `convenio`
3. Capturar datos bancarios del beneficiario en `/admin/ministraciones`
4. Subir documentos: carta compromiso, carátula banco, constancia fiscal, factura
5. Cambiar estado: pendiente → revision → autorizada → pagada

### 4.5 Lista negra (sanciones)

`/admin/lista-negra` → "Registrar sanción"
- Empresa
- Motivo (ej: "No entregó informe final en 20 días hábiles")
- Fecha inicio + fin (vacío = indefinido)

Empresas en lista negra (`activa=true`) NO pueden enviar nuevas solicitudes.

---

## 5. Monitoreo del sistema en vivo

### 5.1 Estado de los servicios

```bash
# Backend (php-fpm)
sudo systemctl status php8.2-fpm

# Frontend (Next.js via supervisor)
sudo supervisorctl status comecyt-next

# WebSockets (Reverb)
sudo supervisorctl status comecyt-reverb

# Queue workers
sudo supervisorctl status comecyt-queue:*

# PostgreSQL
sudo systemctl status postgresql

# nginx
sudo systemctl status nginx
```

### 5.2 Logs en vivo

```bash
# Laravel general
sudo tail -f /var/www/comecyt/apps/api/storage/logs/laravel.log

# Seguridad (login fallidos, lockouts, accesos sospechosos)
sudo tail -f /var/www/comecyt/apps/api/storage/logs/security-*.log

# nginx access
sudo tail -f /var/log/nginx/comecyt-access.log

# nginx errors
sudo tail -f /var/log/nginx/comecyt-error.log

# Reverb WebSocket
sudo tail -f /var/log/comecyt-reverb.log

# Queue workers
sudo tail -f /var/log/comecyt-queue.log
```

### 5.3 Queries útiles en la DB

```bash
sudo -u postgres psql comecyt_prod
```

```sql
-- Solicitudes activas por estado
SELECT estado, COUNT(*) FROM solicitudes WHERE deleted_at IS NULL GROUP BY estado ORDER BY 2 DESC;

-- Asignaciones de evaluadores pendientes
SELECT u.name AS evaluador, COUNT(ae.id) AS pendientes
FROM asignaciones_evaluador ae
JOIN users u ON u.id = ae.evaluador_id
WHERE ae.estado = 'asignado'
GROUP BY u.name;

-- Ministraciones por estado
SELECT estado, COUNT(*), SUM(monto_pagado) FROM ministraciones GROUP BY estado;

-- Últimos logins fallidos (auditoría)
SELECT created_at, user_id, action, ip_address
FROM audit_logs
WHERE action LIKE '%login_failed%'
ORDER BY created_at DESC LIMIT 20;

-- Empresas en lista negra activa
SELECT e.nombre, ln.motivo, ln.fecha_inicio_sancion
FROM lista_negra ln
JOIN empresas e ON e.id = ln.empresa_id
WHERE ln.activa = true;
```

---

## 6. Backups

### 6.1 Backup manual de la DB

```bash
DATE=$(date +%Y%m%d_%H%M%S)
sudo -u postgres pg_dump comecyt_prod | gzip > /backup/db-comecyt-${DATE}.sql.gz
sudo chmod 400 /backup/db-comecyt-${DATE}.sql.gz
sudo chown root:root /backup/db-comecyt-${DATE}.sql.gz
```

### 6.2 Backup de storage (uploads de documentos)

```bash
DATE=$(date +%Y%m%d_%H%M%S)
sudo tar -czf /backup/storage-${DATE}.tar.gz \
    -C /var/www/comecyt/apps/api/storage/app/public .
sudo chmod 400 /backup/storage-${DATE}.tar.gz
```

### 6.3 Backup automatizado (cron diario)

`/etc/cron.daily/comecyt-backup`:

```bash
#!/bin/bash
set -e
BACKUP_DIR=/backup
DATE=$(date +%Y%m%d)
RETAIN_DAYS=30

mkdir -p ${BACKUP_DIR}

# DB
sudo -u postgres pg_dump comecyt_prod | gzip > ${BACKUP_DIR}/db-${DATE}.sql.gz
chmod 400 ${BACKUP_DIR}/db-${DATE}.sql.gz

# Storage
tar -czf ${BACKUP_DIR}/storage-${DATE}.tar.gz \
    -C /var/www/comecyt/apps/api/storage/app/public .
chmod 400 ${BACKUP_DIR}/storage-${DATE}.tar.gz

# Retener solo últimos N días
find ${BACKUP_DIR} -name "db-*.sql.gz" -mtime +${RETAIN_DAYS} -delete
find ${BACKUP_DIR} -name "storage-*.tar.gz" -mtime +${RETAIN_DAYS} -delete

# Opcional: sincronizar con bucket institucional
# rsync -avz ${BACKUP_DIR}/ usuario@backup.comecyt.gob.mx:/backups/comecyt/
```

```bash
sudo chmod +x /etc/cron.daily/comecyt-backup
```

### 6.4 Restaurar de un backup

```bash
# DB
gunzip < /backup/db-20260612.sql.gz | sudo -u postgres psql comecyt_prod

# Storage
sudo rm -rf /var/www/comecyt/apps/api/storage/app/public/*
sudo tar -xzf /backup/storage-20260612.tar.gz \
    -C /var/www/comecyt/apps/api/storage/app/public/
sudo chown -R www-data:www-data /var/www/comecyt/apps/api/storage/app/public/
```

---

## 7. Actualizaciones del sistema

### 7.1 Actualizar el código (deploy de nuevas versiones)

```bash
cd /var/www/comecyt
sudo -u www-data git pull origin main

# Backend
cd apps/api
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo bash deploy.sh

# Frontend
cd /var/www/comecyt/apps/web
sudo -u www-data npm ci --legacy-peer-deps
sudo -u www-data npm run build

# Reiniciar servicios
sudo supervisorctl restart comecyt-next
sudo supervisorctl restart comecyt-reverb
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Verificar
sudo -u www-data php artisan app:deploy-check
```

### 7.2 Aplicar parches de seguridad (Dependabot)

Los PRs de Dependabot llegan automáticamente al repo. Para aplicarlos:

1. Revisar el PR en GitHub
2. Verificar que CI pase (security-sast workflow)
3. Merge a main
4. En el servidor: `git pull origin main` + paso 7.1

### 7.3 Migración de mayor versión (Laravel/Node)

Sigue la guía oficial. Test en staging antes de prod.

---

## 8. Solución de problemas (Troubleshooting)

### 8.1 "Login no funciona" (500 / 401)

```bash
# Verificar JWT secret
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan tinker --execute='echo strlen(env("JWT_SECRET"));'
# Debe ser ≥ 32

# Si está mal o vacío:
sudo -u www-data php artisan jwt:secret --force
sudo -u www-data php artisan config:cache
sudo systemctl reload php8.2-fpm
```

### 8.2 "CORS error" en consola del navegador

```bash
sudo grep CORS_ALLOWED_ORIGINS /var/www/comecyt/apps/api/.env
```

Debe ser exactamente `https://tudominio.gob.mx` (sin trailing slash, sin `*`).

Si lo cambias, recachear:
```bash
sudo -u www-data php artisan config:cache
sudo systemctl reload php8.2-fpm
```

### 8.3 "Página da 502 Bad Gateway"

```bash
# ¿Next.js está vivo?
sudo supervisorctl status comecyt-next
# Si no: sudo supervisorctl restart comecyt-next

# ¿php-fpm está vivo?
sudo systemctl status php8.2-fpm
# Si no: sudo systemctl restart php8.2-fpm

# ¿nginx puede llegar?
curl -I http://127.0.0.1:3000
curl -I http://127.0.0.1:9000
```

### 8.4 "Subida de archivo falla con 413 Request Entity Too Large"

Editar nginx:
```nginx
client_max_body_size 10M;  # subir según necesidad
```

Y reload: `sudo systemctl reload nginx`

### 8.5 "Notificaciones en tiempo real no funcionan"

```bash
# Verificar Reverb
sudo supervisorctl status comecyt-reverb
sudo tail /var/log/comecyt-reverb.log

# Verificar nginx WebSocket proxy
sudo grep -A5 "location /app/" /etc/nginx/sites-enabled/comecyt

# Verificar firewall
sudo ufw status | grep 443
```

El frontend usa `wss://tudominio.gob.mx/app/...` que nginx proxiea a `127.0.0.1:8080`.

### 8.6 "Emails no se envían"

```bash
# Verificar config SMTP
sudo grep "^MAIL_" /var/www/comecyt/apps/api/.env

# Test manual
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan tinker --execute='
  Mail::raw("Test desde COMECYT", function($m) {
    $m->to("tu@email.com")->subject("Test SMTP");
  });
'

# Ver últimos errores
sudo tail -50 /var/www/comecyt/apps/api/storage/logs/laravel.log | grep -i mail
```

### 8.7 "DB lenta / queries pesadas"

```bash
# Ver queries en vivo
sudo -u postgres psql comecyt_prod -c "
  SELECT pid, now() - query_start AS duration, query
  FROM pg_stat_activity
  WHERE state = 'active' AND now() - query_start > interval '1 second'
  ORDER BY duration DESC;
"

# Forzar reload de queries cacheadas
sudo -u www-data php artisan cache:clear
```

### 8.8 "Disco lleno"

```bash
sudo du -sh /var/www/comecyt/* /var/log/* /backup/* | sort -hr | head

# Limpiar logs viejos
sudo find /var/www/comecyt/apps/api/storage/logs/ -name "*.log" -mtime +30 -delete
sudo find /var/log/ -name "*.gz" -mtime +60 -delete
```

---

## 9. Incidentes de seguridad

Ver runbook completo en `docs/security/incident-response.md`.

**Lo crítico de memorizar:**

| Incidente | Comando inmediato |
|---|---|
| Sospecha de JWT fugado | `php artisan jwt:secret --force && php artisan config:cache && systemctl restart php8.2-fpm` |
| Cuenta admin comprometida | Bloquear cuenta + rotar JWT (también) + auditar AuditLog |
| Brecha de datos personales (LFPDPPP) | Snapshot DB+logs (preservar evidencia), notificar DPO en ≤72h |
| DDoS sostenido | `php artisan down --secret=$(openssl rand -hex 16)` + bloquear IPs en iptables |

---

## 10. Roles y responsabilidades sugeridas

| Rol | Responsable | Frecuencia |
|---|---|---|
| **Backup verification** | Equipo TIC | Mensual — restaurar a DB de prueba |
| **Log review** | Líder TIC | Semanal — revisar security logs |
| **Patch management** | Dev senior | Cuando llegan PRs de Dependabot |
| **Penetration test** | Auditor externo | Anual |
| **DPO review (LFPDPPP)** | Oficial de Datos | Trimestral |
| **User audit** | Líder TIC | Trimestral — revisar admins activos |

---

## 11. Métricas a monitorear (KPIs operativos)

| Métrica | Target | Alerta si... |
|---|---|---|
| Uptime | > 99.5% | < 99% en cualquier mes |
| Latencia p95 API | < 500ms | > 1s sostenido |
| Tasa de error 5xx | < 0.1% | > 1% en 5 min |
| Logins fallidos / hora | < 50 | > 200 en 1 hora |
| Solicitudes en `enviada` por > 7 días | 0 | > 5 |
| Asignaciones evaluador vencidas | 0 | > 3 |
| Disk usage | < 70% | > 85% |
| DB connections | < 50 | > 90% del pool |

---

## Apéndice — Acceso de emergencia

Si el sistema está completamente caído y necesitas acceder DB directamente:

```bash
sudo -u postgres psql comecyt_prod
```

Si necesitas bypassear el JWT temporalmente (debug):
```bash
# NUNCA en producción real — sólo en dev/staging
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan tinker
> $user = User::find(1);
> $token = JWTAuth::fromUser($user);
> echo $token;
```

Si necesitas regenerar el admin root:
```bash
cd /var/www/comecyt/apps/api
sudo -u www-data php artisan tinker --execute='
  $admin = App\Models\User::find(1);
  $admin->password = Hash::make("NUEVA_PASSWORD_FUERTE");
  $admin->save();
  echo "Password actualizada\n";
'
```

---

> **Soporte técnico:** Issues en el repo o equipo de TIC del COMECYT.
> **Soporte de seguridad:** Ver `docs/security/incident-response.md` §1 (cadena de mando).
