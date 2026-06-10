# Runbook de Respuesta a Incidentes — COMECYT

> **Versión:** 1.0 — 2026-06-10
> **Audiencia:** Equipo de operaciones, oficial de protección de datos, dirección de TIC.
> **Alcance:** Sistema "Gestión de Proyectos de Desarrollo Tecnológico y Vinculación" (apps/api + apps/web), DB PostgreSQL, servidor bare-metal.
> **Marco legal:** LFPDPPP (México), LGPDPPSO, lineamientos institucionales del Estado de México.

---

## Tabla de contenido

1. [Cadena de mando y contactos](#1-cadena-de-mando-y-contactos)
2. [Clasificación de severidad](#2-clasificación-de-severidad)
3. [Detección y triage](#3-detección-y-triage)
4. [Playbook A — Fuga / robo de credenciales (JWT secret, DB, admin)](#4-playbook-a--fuga--robo-de-credenciales)
5. [Playbook B — Brecha de datos personales (LFPDPPP)](#5-playbook-b--brecha-de-datos-personales-lfpdppp)
6. [Playbook C — DDoS sostenido](#6-playbook-c--ddos-sostenido)
7. [Playbook D — Compromiso del servidor](#7-playbook-d--compromiso-del-servidor)
8. [Plan calendarizado de rotación de secretos](#8-plan-calendarizado-de-rotación-de-secretos)
9. [Lecciones aprendidas y mejora continua](#9-lecciones-aprendidas)

---

## 1. Cadena de mando y contactos

| Rol | Responsabilidad |
|---|---|
| **Incident Commander (IC)** | Dirige el incidente. Default: Líder de Infraestructura COMECYT. |
| **Comunicaciones** | Sube reportes a dirección y, si aplica, al INAI/autoridad. |
| **Forensic Lead** | Preserva evidencia, no destruye logs hasta cierre. |
| **DPO (Oficial de Datos)** | Decide si la brecha es notificable bajo LFPDPPP. |
| **Operaciones** | Aplica contención (bloquear IPs, deshabilitar cuentas, restaurar de backup). |

> ⚠ Lista de teléfonos/correos viva en una hoja **fuera de este repo** (no commitearla).
> Ubicación recomendada: vault institucional + impresa en la sala de operaciones.

---

## 2. Clasificación de severidad

| Sev | Definición | Tiempo respuesta |
|---|---|---|
| **SEV-1** | Fuga de PII, compromiso de admin, sistema fuera de línea > 30 min, ransomware. | < 30 min |
| **SEV-2** | Vulnerabilidad explotable confirmada, intentos masivos de fuerza bruta, fuga de credenciales no-admin. | < 2 h |
| **SEV-3** | Acceso no autorizado a un solo recurso, escaneo anómalo persistente. | < 24 h |
| **SEV-4** | Hallazgo de scanner, vulnerabilidad teórica sin explotación observada. | Próximo sprint |

---

## 3. Detección y triage

Señales que disparan investigación inmediata:

- Alerta de **AuditLog**: spike de operaciones admin fuera del horario laboral.
- Logs de Laravel (`storage/logs/laravel.log` + `storage/logs/security.log`):
  ```bash
  grep -E "(AuthLoginRateLimit|account_locked|jwt_invalid|csrf_failed)" \
    /var/www/api/storage/logs/security-*.log | tail -100
  ```
- Métricas: latencia p95 > 3s sostenido, > 500 5xx/min.
- `CircuitBreakerMiddleware` reporta múltiples `OPEN`.
- Reporte externo (usuario, INAI, INCIBE).

**Primer paso siempre:** confirmar que no es falso positivo (mantenimiento programado, prueba de pentest autorizada).

---

## 4. Playbook A — Fuga / robo de credenciales

### 4.1 Si se sospecha que `JWT_SECRET` fue expuesto

> Impacto: cualquier atacante puede forjar tokens para cualquier usuario, incluido admin.

**Acciones inmediatas (≤ 5 min):**

```bash
# 1. En el servidor de la API:
cd /var/www/api

# 2. Rotar el JWT secret (esto invalida TODOS los tokens emitidos)
php artisan jwt:secret --force

# 3. Limpiar la caché de config para que el nuevo secret se cargue
php artisan config:clear
php artisan config:cache

# 4. Reiniciar PHP-FPM para asegurar que workers viejos no usen el secret previo
sudo systemctl restart php8.2-fpm

# 5. Forzar logout global desde la app (todos los usuarios deben re-login)
#    Como JWT es stateless, la rotación del secret automáticamente invalida
#    todos los tokens. Anunciar a usuarios via canal interno.
```

**Comunicación obligatoria:**
- Email/Slack interno: "Mantenimiento de seguridad. Por favor, vuelvan a iniciar sesión."
- NO mencionar la palabra "fuga" hasta confirmar el alcance.

**Investigación post-rotación:**
- Auditar `git log --all -p | grep -iE 'JWT_SECRET|sk_|password='` para identificar commit del leak.
- Revisar GitHub secret-scanning alerts.
- Si el secret se filtró en un repo público: notificar a SCT-CERT institucional.

---

### 4.2 Si se sospecha que `DB_PASSWORD` fue expuesto

```bash
# 1. Rotar password en Postgres (genera 32 chars random)
NEW_PASS=$(openssl rand -base64 32)
sudo -u postgres psql -c "ALTER USER comecyt_user WITH PASSWORD '${NEW_PASS}';"

# 2. Actualizar .env del servidor
sudo nano /var/www/api/.env  # editar línea DB_PASSWORD=…

# 3. Verificar y reiniciar
cd /var/www/api
php artisan config:clear && php artisan config:cache
php artisan tinker --execute='\DB::connection()->getPdo(); echo "DB OK\n";'
sudo systemctl restart php8.2-fpm

# 4. Permisos restrictivos del .env
chmod 600 /var/www/api/.env
chown www-data:www-data /var/www/api/.env
```

**Detección de uso del secret filtrado:**
```bash
# Revisar logs de Postgres por conexiones desde IPs no autorizadas
sudo grep -E "connection authorized.*comecyt_user" /var/log/postgresql/postgresql-*.log \
  | awk '{print $NF}' | sort -u
```

---

### 4.3 Si se sospecha compromiso de cuenta admin

```bash
# 1. Bloquear inmediatamente la cuenta sospechosa
cd /var/www/api
php artisan tinker --execute="\\App\\Models\\User::where('email', 'admin@comecyt.gob.mx')->update(['activo' => false, 'password' => bcrypt(\\Illuminate\\Support\\Str::random(64))]);"

# 2. Invalidar todos los tokens del usuario forzando rotación del JWT_SECRET (sección 4.1)
#    (esto es global, afecta a todos — aceptable por SEV-1)

# 3. Auditar acciones recientes del admin
php artisan tinker --execute="\\App\\Models\\AuditLog::where('user_id', 1)->where('created_at', '>=', now()->subDays(7))->get(['action','subject_type','subject_id','ip_address','created_at'])->each(fn(\$l) => print_r(\$l->toArray()));"

# 4. Crear nuevo admin desde consola (no usar UI hasta confirmar limpieza)
php artisan tinker --execute="
\\App\\Models\\User::create([
  'name' => 'Admin Recovery',
  'email' => 'recovery@comecyt.gob.mx',
  'password' => bcrypt('CAMBIAR_EN_PRIMER_LOGIN_'.\\Illuminate\\Support\\Str::random(8)),
  'rol_id' => 1,
  'activo' => true,
]);"
```

---

## 5. Playbook B — Brecha de datos personales (LFPDPPP)

> Marco aplicable: art. 20 LFPDPPP — el responsable debe notificar al titular sin demora cuando la vulneración afecte significativamente sus derechos. INAI puede sancionar el incumplimiento.

### 5.1 Triage inmediato (≤ 30 min)

1. **Confirmar la brecha:** ¿hay evidencia objetiva (logs, capturas, denuncia) de que datos personales fueron accedidos por un tercero no autorizado?
2. **Determinar el alcance** — categorías de datos afectados:
   - **Datos identificativos** (nombre, CURP, RFC, email, teléfono) → notificar.
   - **Datos financieros** (CLABE, cuenta bancaria) → notificar + alertar a CONDUSEF.
   - **Datos sensibles** (salud, opiniones políticas, datos biométricos) → notificar de inmediato.
3. **Contar titulares afectados.** Si > 100, considerar comunicado público.

### 5.2 Notificación al titular (≤ 72h ideal)

Plantilla (vive en `docs/security/templates/notificacion-brecha.md` — pendiente de crear con el equipo de comunicación):

```
Asunto: Comunicación importante sobre tus datos personales — COMECYT

Estimado/a [nombre],

El [fecha de la brecha], identificamos un incidente de seguridad que afectó
información que tienes registrada en el sistema COMECYT. Los datos involucrados
fueron: [lista mínima necesaria].

Acciones tomadas:
- [contención]
- [investigación forense]
- [reforzamiento de controles]

Recomendaciones para ti:
- Cambia tu contraseña en el sistema.
- Activa el segundo factor de autenticación si no lo tienes.
- Mantente alerta a comunicaciones sospechosas.

Si tienes preguntas, contacta a nuestro DPO en privacidad@comecyt.gob.mx
o llama a [teléfono].

Atentamente,
[Nombre del responsable]
```

### 5.3 Notificación al INAI

- Si los datos afectados son **sensibles** o el número de afectados es significativo: oficio a INAI dentro de los plazos del aviso de privacidad institucional.
- Documento debe incluir: descripción del incidente, datos afectados, medidas correctivas, medidas para evitar recurrencia.

### 5.4 Preservación de evidencia

```bash
# 1. Snapshot íntegro de la BD ANTES de cualquier acción correctiva
sudo -u postgres pg_dump comecyt_dev > /backup/forensic/db-$(date +%Y%m%d_%H%M%S).sql
sudo -u postgres pg_dumpall > /backup/forensic/db-global-$(date +%Y%m%d_%H%M%S).sql

# 2. Snapshot de logs de los últimos 30 días
sudo tar -czf /backup/forensic/logs-$(date +%Y%m%d_%H%M%S).tar.gz \
  /var/www/api/storage/logs/ \
  /var/log/nginx/ \
  /var/log/postgresql/ \
  /var/log/auth.log* \
  /var/log/syslog*

# 3. Hash de integridad
sha256sum /backup/forensic/* > /backup/forensic/SHA256SUMS-$(date +%Y%m%d).txt
sudo chmod 400 /backup/forensic/*
sudo chown root:root /backup/forensic/*
```

**Retención:** mínimo 5 años para incidentes con datos personales.

---

## 6. Playbook C — DDoS sostenido

`ApiGatewayMiddleware` ya activa lockdown ante > 1000 IPs únicas/60s. Si persiste:

```bash
# 1. Identificar TOP IPs atacantes
sudo tail -10000 /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -50

# 2. Bloquear las top 20 vía iptables
for IP in $(sudo tail -10000 /var/log/nginx/access.log | awk '{print $1}' | sort | uniq -c | sort -rn | head -20 | awk '{print $2}'); do
  sudo iptables -A INPUT -s "$IP" -j DROP
done

# 3. Habilitar modo "static maintenance" (sirve sólo página de mantenimiento HTML estática)
cd /var/www/api && php artisan down --secret="$(openssl rand -hex 16)"
# Guardar el secret y compartir con admin para que puedan acceder vía ?token=<secret>

# 4. Pedir mitigación a la CDN/proveedor de Internet (Telmex/Totalplay/etc.)
#    si el ataque viene del ASN ajeno.
```

---

## 7. Playbook D — Compromiso del servidor

Indicios: cuentas SSH nuevas, procesos extraños, hashes de archivos modificados.

```bash
# 1. AISLAR — desconectar de la red sin apagar (preserva memoria)
sudo iptables -A INPUT -j DROP
sudo iptables -A OUTPUT -j DROP
# Excepción para acceso de operaciones desde IP confiable:
sudo iptables -I INPUT -s <IP-DEL-IC> -j ACCEPT
sudo iptables -I OUTPUT -d <IP-DEL-IC> -j ACCEPT

# 2. Capturar estado en vivo
sudo ps auxf > /backup/forensic/ps-$(date +%Y%m%d_%H%M%S).txt
sudo netstat -tulpn > /backup/forensic/netstat-$(date +%Y%m%d_%H%M%S).txt
sudo ss -tunap > /backup/forensic/ss-$(date +%Y%m%d_%H%M%S).txt
sudo last -F | head -100 > /backup/forensic/last-$(date +%Y%m%d_%H%M%S).txt
sudo lsof -nP > /backup/forensic/lsof-$(date +%Y%m%d_%H%M%S).txt

# 3. NO reinstalar sobre el server comprometido. Restaurar a server limpio
#    desde backup INMUTABLE previo al compromiso.

# 4. Cambiar TODOS los secretos (sección 8 — rotación completa).
```

---

## 8. Plan calendarizado de rotación de secretos

| Secreto | Rotación regular | Rotación inmediata si... |
|---|---|---|
| `JWT_SECRET` | Anual | Sospecha de fuga, cambio de IC. |
| `DB_PASSWORD` | Cada 90 días | Compromiso del servidor, salida de personal con acceso. |
| `APP_KEY` | Anual (con migración planeada — invalida sesiones encriptadas) | Compromiso del servidor. |
| `REVERB_APP_SECRET` | Cada 6 meses | Compromiso, despublicación accidental. |
| `VAPID_PRIVATE_KEY` | Anual | Compromiso del servidor. |
| SMTP credentials | Anual o cuando el proveedor lo exija | Spam saliendo del relay institucional. |
| Token de GitHub (deploy/CI) | Cada 90 días | Cualquier exposición en logs/PR. |
| Certificado TLS | Anual (Let's Encrypt: 90 días automático) | Sospecha de compromiso de clave privada. |

### Procedimiento estándar de rotación

```bash
# Variables relevantes
SERVER=produccion-comecyt
APP_DIR=/var/www/api

ssh "$SERVER" "cd ${APP_DIR} && \
  php artisan jwt:secret --force && \
  php artisan config:clear && \
  php artisan config:cache && \
  sudo systemctl restart php8.2-fpm && \
  echo '[OK] JWT rotation completed at' \$(date)"

# Actualizar evidencia en el AuditLog institucional (manualmente):
ssh "$SERVER" "cd ${APP_DIR} && php artisan tinker --execute=\"
\\App\\Models\\AuditLog::create([
  'user_id' => null,
  'action' => 'secret_rotation',
  'subject_type' => 'JWT_SECRET',
  'metadata' => ['rotated_at' => now()->toIso8601String(), 'reason' => 'scheduled'],
  'created_at' => now(),
]);
\""
```

### Backup de secretos (recuperación post-rotación)

- Vault institucional cifrado (HashiCorp Vault o equivalente que la TIC del EdoMex provea).
- NUNCA en repositorio git.
- NUNCA en chats/correos sin cifrar.
- Acceso al vault auditado con MFA obligatorio.

---

## 9. Lecciones aprendidas

Después de **cada SEV-1 o SEV-2**, en máximo 5 días hábiles:

1. **Post-mortem sin culpa** (blameless). Documento en `docs/security/post-mortems/YYYY-MM-DD-titulo.md`.
2. Identificar la **causa raíz** (no la culpa).
3. Listar **mejoras concretas** con dueño y fecha de cierre:
   - Cambios técnicos (nuevo control, hardening, monitoring).
   - Cambios de proceso (revisión obligatoria, checklist).
   - Cambios de capacitación (entrenamiento del equipo).
4. **Calendar el seguimiento**: 1 mes después, validar que las mejoras se aplicaron.

> Si el incidente involucró datos personales, incluir como anexo la comunicación al titular y/o al INAI.

---

## Anexo A — Comando rápido de salud del sistema

```bash
# Verificación rápida del estado de seguridad post-incidente
cd /var/www/api
php artisan app:deploy-check       # Reporta errores/warnings de configuración

# Verificar integridad de archivos críticos
sudo find /var/www/api -name "*.php" -newer /tmp/baseline 2>/dev/null | head -50

# Procesos sospechosos
ps auxf | grep -v "$(whoami)\|nginx\|php-fpm\|postgres\|sshd" | grep -v "^USER"
```

## Anexo B — Referencias

- LFPDPPP — Ley Federal de Protección de Datos Personales en Posesión de los Particulares.
- LGPDPPSO — Ley General de Protección de Datos Personales en Posesión de Sujetos Obligados.
- NIST SP 800-61 r2 — Computer Security Incident Handling Guide.
- INAI — Lineamientos generales en materia de notificación de vulneraciones.

---

> **Última revisión:** 2026-06-10. Revisar trimestralmente y tras cualquier incidente.
