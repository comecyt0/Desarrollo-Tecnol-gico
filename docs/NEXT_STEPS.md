# 📋 Próximos pasos para tener el sistema 100% en producción

> **Audiencia:** Líder TIC del COMECYT que va a operar el deploy.
> **Estado al 2026-06-30:** El sistema **YA ESTÁ DESPLEGADO y accesible por IP** (`http://10.250.36.241/login`). Mientras el DNS no resuelva, la configuración es temporal (CORS + cookies relajados para HTTP). Ver `docs/WINDOWS_DEPLOYMENT.md` §22 para el proceso de transición al dominio.

---

## ✅ COMPLETADO — 2026-06-25

| Tarea | Detalle |
|---|---|
| ✅ Deploy en servidor Windows Server 2022 | Stack nativo IIS+PHP 8.4+Next.js 16+PostgreSQL 18+NSSM |
| ✅ Base de datos inicializada | Migrations + seeders ejecutados, 4 usuarios creados |
| ✅ Servicios corriendo como Windows Services | comecyt-web, comecyt-reverb, comecyt-queue, comecyt-scheduler |
| ✅ WebSocket Reverb via IIS/ARR | `wss://dominio/app/{key}` → 101 verificado |
| ✅ 2FA TOTP en cuenta admin | Activo con `pragmarx/google2fa` |
| ✅ Backups diarios PostgreSQL | Tarea programada SYSTEM, 02:00, retención 14 días |
| ✅ Puertos 80/443 escuchando | Windows Firewall rule activa |
| ✅ PATs de GitHub revocados | Tokens de sesiones 2026-06-25 (`ghp_…CNBu`) y 2026-06-30 (`ghp_…UdS7`) revocados |
| ✅ Documentación Windows | `docs/WINDOWS_DEPLOYMENT.md` §1–§21 en `main` |
| ✅ Dominio definitivo configurado | `comecyt-sistemas.edomex.gob.mx` en todos los archivos de config y docs |
| ✅ Cert autofirmado confiable localmente | `New-SelfSignedCertificate` → Trusted Root CA → candado sin warning en el servidor |
| ✅ Acceso por IP habilitado para pruebas internas | `http://10.250.36.241/login` — CSS/JS corregido (standalone copy), CORS + cookies ajustados; ver §22 para revertir al dominio |

---

## 🔴 PENDIENTE URGENTE

### P-1 — DNS: apuntar dominio al servidor

**Dominio:** `comecyt-sistemas.edomex.gob.mx`

1. Obtener la IP pública del servidor de Infra/OpenStack
2. Crear registro `A` en el DNS institucional apuntando a esa IP
3. Abrir puertos 80 y 443 en el security group de OpenStack
4. Verificar resolución: `Resolve-DnsName comecyt-sistemas.edomex.gob.mx`

> **Mientras tanto** el sistema es accesible para pruebas internas vía `http://10.250.36.241/login` (configuración temporal, ver §22.2 de `WINDOWS_DEPLOYMENT.md`).

**Una vez que el DNS resuelva, seguir el checklist de §22.3:**

```
☐ Restaurar CORS_ALLOWED_ORIGINS, COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_SAME_SITE en apps/api/.env
☐ Actualizar NEXT_PUBLIC_* en apps/web/.env.local al dominio HTTPS
☐ npm run build  →  copiar .next/static y public/ a .next/standalone/
☐ artisan config:clear && Restart-Service comecyt-web, comecyt-queue, comecyt-scheduler
☐ Borrar línea del hosts: 127.0.0.1  comecyt-sistemas.edomex.gob.mx
☐ Ejecutar emitir-cert-letsencrypt.ps1
☐ Verificar https://comecyt-sistemas.edomex.gob.mx/login → candado verde
```

---

### P-2 — Certificado TLS (Let's Encrypt)

**Solo hacer DESPUÉS de que el DNS resuelva correctamente.**

1. Quitar la línea temporal del `hosts`:
   `C:\Windows\System32\drivers\etc\hosts` → borrar `127.0.0.1 comecyt-sistemas.edomex.gob.mx`
2. Ejecutar el helper:
   ```powershell
   & C:\comecyt\emitir-cert-letsencrypt.ps1
   ```
3. win-acme instala el cert en el binding 443 de IIS y crea renovación automática (~60 días)

---

### P-3 — SMTP para notificaciones

Configurar en `C:\comecyt\apps\api\.env`:

```
MAIL_MAILER=smtp
MAIL_HOST=smtp.comecyt.gob.mx        # o el relay institucional
MAIL_PORT=587
MAIL_USERNAME=noreply@comecyt.gob.mx
MAIL_PASSWORD=<contraseña>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@comecyt.gob.mx
MAIL_FROM_NAME="COMECYT"
```

Después reiniciar la cola: `Restart-Service comecyt-queue`

---

## 🟡 IMPORTANTE (antes de apertura pública)

### P-4 — Reemplazar usuarios de prueba

Los usuarios actuales (`asd@asd.com`, `evaluadorr@uaemex.mx`, `solicitante@institucion.mx`) son de prueba. Antes de abrir a usuarios reales:
1. Acceder como admin a `/admin/dashboard` → Usuarios
2. Crear cuentas reales para el personal de COMECYT
3. Desactivar o eliminar los usuarios de prueba

### P-5 — Imágenes del carrusel del login

Los slides del carrusel tienen `imagen_url: null` (gradiente puro).
Para agregar imágenes reales: `/admin/carrusel` → subir JPG/WebP 1920×1080, < 2 MB por imagen.

### P-6 — Logo oficial Estado de México

El logo dual está deshabilitado (`NEXT_PUBLIC_EDOMEX_LOGO_URL=none`).
Para habilitarlo con la imagen oficial: ver `docs/WINDOWS_DEPLOYMENT.md` §16.

### P-7 — Aviso de Privacidad y Términos

Las páginas `/privacidad` y `/terminos` son plantillas. Deben ser revisadas y aprobadas por:
- Unidad de Transparencia (Aviso de Privacidad LFPDPPP)
- Área Jurídica (Términos y Condiciones)

Archivo: `apps/web/src/app/(legal)/privacidad/page.tsx` línea ~30 — llenar domicilio institucional.

### P-8 — Visibilidad del repo en GitHub

El repo `comecyt0/Desarrollo-Tecnol-gico` está actualmente **público**. Para un sistema gubernamental que procesa datos personales, lo recomendable es **privado**. Decidir con la dirección institucional antes de la apertura.

### P-9 — Branch protection en GitHub

Configurar en `github.com/comecyt0/Desarrollo-Tecnol-gico/settings/branches`:
- Requerir PR + aprobación antes de merge a `main`
- Requerir que pasen los status checks (CI)
- Deshabilitar force push sobre `main`

---

## 🟢 COMPLETADO (sesiones anteriores — 2026-06-14)

### ~~BLOQUE 1 — URGENTE (HACER YA, antes de cualquier otra cosa)~~

### 1.1 Revocar los 2 PATs que se expusieron durante la sesión

Durante esta sesión se compartieron 2 Personal Access Tokens en el chat (necesarios para que yo pudiera empujar al repo). **Ya quedaron expuestos en el historial del chat y en capturas de pantalla**. Hay que revocarlos.

**Pasos:**

1. Loguéate en GitHub como **`lumiaaisolutions`**
2. Ve a [github.com/settings/tokens](https://github.com/settings/tokens)
3. Busca y haz click en **Delete** sobre estos dos tokens:
   - `ghp_wzLd…3ZK`
   - `ghp_mSRz…3fxif2`
4. Confirma la eliminación

**Tiempo:** 2 minutos.

> ⚠️ Si no los revocas, cualquier scraper de chats compartidos los va a robar y podría:
> - Empujar código malicioso al repo
> - Borrar el repo entero
> - Sacar credenciales de tus otros repos privados

---

### 1.2 Decidir visibilidad del repo (público vs privado)

**Actualmente el repo `comecyt0/Desarrollo-Tecnol-gico` está PÚBLICO.**

Para un sistema gubernamental que procesa datos personales bajo LFPDPPP, lo estándar es **privado**.

**Decisión a tomar (con la dirección institucional):**

| Opción | Cuándo elegir |
|---|---|
| 🔒 **Privado** (recomendado) | Si quieres control total sobre quién ve el código. Estándar para gobierno. |
| 🌐 **Público** | Si la institución decide ser transparente sobre el código (filosofía open-source). |

**Para cambiar a privado** (recomendado):

1. Logueado como `comecyt0`, ve a [github.com/comecyt0/Desarrollo-Tecnol-gico/settings](https://github.com/comecyt0/Desarrollo-Tecnol-gico/settings)
2. Scroll hasta el final → **"Danger Zone"**
3. **Change repository visibility** → **Make private**
4. Confirma escribiendo `comecyt0/Desarrollo-Tecnol-gico`

> Si lo dejas público, asegúrate de que TODOS los `.env` reales y secrets estén bien guardados fuera del repo.

**Tiempo:** 1 minuto.

---

### 1.3 Configurar Branch Protection en `main`

Actualmente cualquier collaborator con permiso `write` puede hacer `git push --force` sobre `main` y destruir el histórico. Eso es un riesgo para producción.

**Pasos:**

1. Ve a [github.com/comecyt0/Desarrollo-Tecnol-gico/settings/branches](https://github.com/comecyt0/Desarrollo-Tecnol-gico/settings/branches)
2. **Add branch protection rule**
3. **Branch name pattern:** `main`
4. Activa estas casillas:

   ```
   ☑ Require a pull request before merging
       ☑ Require approvals: 1
       ☑ Dismiss stale pull request approvals when new commits are pushed
       ☑ Require review from Code Owners
   ☑ Require status checks to pass before merging
       ☑ Require branches to be up to date before merging
       Status checks requeridos (búscalos y agrégalos):
         · tests / Backend Pest/PHPUnit
         · tests / Frontend Vitest
         · tests / Frontend build (Next.js)
         · Security SAST / Semgrep — OWASP Top 10 + security-audit
         · Security SAST / Gitleaks — Secret scanning
         · Security SAST / NPM audit (Node supply chain)
   ☑ Require conversation resolution before merging
   ☑ Require linear history
   ☑ Do not allow bypassing the above settings
   ☑ Restrict who can push to matching branches
   ☐ Allow force pushes  ← DESACTIVADO
   ☐ Allow deletions     ← DESACTIVADO
   ```

5. **Save**

**Tiempo:** 3 minutos.

> Los status checks aparecerán para seleccionar **DESPUÉS** del primer PR/push que dispare los workflows (puede ser este mismo). Si no aparecen aún, marca los essentials y reabre la regla más tarde.

---

## 🟡 BLOQUE 2 — IMPORTANTE (antes del deploy real)

### 2.1 Crear los emails institucionales referenciados en docs

El sistema y la documentación apuntan a estos correos. Si no existen, los usuarios no podrán contactar soporte.

| Correo | Para qué se usa | Documentos que lo mencionan |
|---|---|---|
| `soporte@comecyt.gob.mx` | Soporte general | README, OPERATIONS, USER_GUIDE, SECURITY |
| `seguridad@comecyt.gob.mx` | Reportes de vulnerabilidades | SECURITY.md |
| `administracion@comecyt.gob.mx` | Datos personales / ARCO / Privacidad | LICENSE, /privacidad |
| `noreply@comecyt.gob.mx` | Remitente de notificaciones automáticas | .env.example (`MAIL_FROM_ADDRESS`) |

**Coordinar con TIC del Estado de México** para crear estas direcciones. Mientras tanto puedes usar aliases temporales hacia tu correo personal — pero **antes de producción real, los emails institucionales deben existir**.

---

### 2.2 Completar el Aviso de Privacidad LFPDPPP

La página `/privacidad` que generé es una **plantilla técnica**. Tiene un placeholder explícito que debe llenarse:

**Archivo:** `apps/web/src/app/(legal)/privacidad/page.tsx` línea ~30

```tsx
<strong>Domicilio:</strong> [pendiente — capturar domicilio institucional formal]<br />
```

**Acciones:**

1. Llenar el domicilio fiscal/institucional real del COMECYT
2. **Que la Unidad de Transparencia del COMECYT revise y apruebe** el texto completo antes de publicar
3. Una vez aprobado, hacer commit:
   ```bash
   git add apps/web/src/app/(legal)/privacidad/page.tsx
   git commit -m "docs(legal): aprobar texto definitivo del aviso de privacidad LFPDPPP"
   ```

> Equivalente para `/terminos` — el texto también es plantilla, debe revisarlo el área jurídica del COMECYT.

---

### 2.3 Acceso al servidor de producción (escritorio remoto)

Necesitas tener listo antes del deploy:

- [ ] IP/dominio del servidor remoto
- [ ] Acceso SSH (usuario + contraseña o llave)
- [ ] Permisos de `sudo` para instalar paquetes
- [ ] Dominio público (ej. `apoyos.comecyt.gob.mx`) apuntando al servidor
- [ ] Registros DNS configurados (A record + AAAA si IPv6)
- [ ] Puertos 80 y 443 abiertos al internet (para Let's Encrypt + tráfico HTTPS)
- [ ] Backup automático del servidor configurado por TIC del Estado

---

## 🟢 BLOQUE 3 — DEPLOY EN PRODUCCIÓN

Una vez resueltos los bloques 1 y 2, sigue la guía maestra:

### 3.1 Lee la guía completa
👉 **[`docs/DEPLOYMENT.md`](DEPLOYMENT.md)** — 60-90 minutos paso a paso

### 3.2 Resumen TL;DR del deploy

```bash
# Conéctate al servidor por SSH
ssh tu_usuario@servidor.gob.mx

# Instalar paquetes base (Ubuntu 22.04)
sudo apt update && sudo apt install -y nginx postgresql-18 php8.2-fpm \
    php8.2-{cli,common,pgsql,mbstring,xml,curl,zip,bcmath,gd,intl,sodium} \
    composer git nodejs npm certbot python3-certbot-nginx supervisor

# Crear DB
sudo -u postgres psql <<SQL
CREATE USER comecyt_user WITH PASSWORD '$(openssl rand -base64 32)';
CREATE DATABASE comecyt_prod OWNER comecyt_user ENCODING 'UTF8';
SQL

# Clone
sudo mkdir -p /var/www && cd /var/www
sudo git clone https://github.com/comecyt0/Desarrollo-Tecnol-gico.git comecyt
sudo chown -R www-data:www-data comecyt

# Backend
cd /var/www/comecyt/apps/api
sudo -u www-data cp .env.example .env
sudo nano .env  # Configura según DEPLOYMENT.md §4.3
sudo chmod 600 .env
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan jwt:secret --force
sudo bash deploy.sh

# Frontend
cd /var/www/comecyt/apps/web
sudo -u www-data cp .env.example .env.local
sudo nano .env.local
sudo -u www-data npm ci --legacy-peer-deps
sudo -u www-data npm run build

# nginx + SSL
sudo cp /var/www/comecyt/docs/deploy-templates/nginx-comecyt.conf \
        /etc/nginx/sites-available/comecyt
# Editar el dominio en el archivo:
sudo sed -i 's/tudominio.gob.mx/apoyos.comecyt.gob.mx/g' \
        /etc/nginx/sites-available/comecyt
sudo ln -s /etc/nginx/sites-available/comecyt /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d apoyos.comecyt.gob.mx \
        --email administracion@comecyt.gob.mx --agree-tos --no-eff-email --redirect

# Supervisor
sudo cp /var/www/comecyt/docs/deploy-templates/supervisor-*.conf \
        /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update

# Validar
cd /var/www/comecyt/apps/api && sudo -u www-data php artisan app:deploy-check
# Debe dar 0 errores
```

### 3.3 Verificación post-deploy

```bash
# Health check externo
HEALTH_TOKEN=$(sudo grep '^HEALTH_TOKEN=' /var/www/comecyt/apps/api/.env | cut -d= -f2)
curl -s -H "X-Health-Token: ${HEALTH_TOKEN}" \
     https://apoyos.comecyt.gob.mx/api/health | jq

# Login admin (manual desde navegador)
# https://apoyos.comecyt.gob.mx/login
# → Email del admin que generó DatabaseSeeder
# → CAMBIA LA CONTRASEÑA EN EL PRIMER LOGIN
```

---

## 🔵 BLOQUE 4 — POST-DEPLOY (semana 1)

### 4.1 Configurar monitoreo externo

**Opciones gratuitas:**

- **UptimeRobot** ([uptimerobot.com](https://uptimerobot.com))
  - URL: `https://apoyos.comecyt.gob.mx/api/health`
  - Header: `X-Health-Token: <tu_token>`
  - Frecuencia: cada 5 minutos
  - Alertas a: email del DPO + email TIC

- **Zabbix institucional** si el Estado tiene infraestructura propia

### 4.2 Configurar SMTP institucional

`.env` actualmente probablemente usa `log` o un SMTP de prueba. Cambiar a:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.comecyt.gob.mx
MAIL_PORT=587
MAIL_USERNAME=noreply@comecyt.gob.mx
MAIL_PASSWORD=<password real>
MAIL_FROM_ADDRESS=noreply@comecyt.gob.mx
MAIL_FROM_NAME="COMECYT"
MAIL_ENCRYPTION=tls
```

Luego:
```bash
sudo -u www-data php artisan config:cache
sudo systemctl restart php8.2-fpm
```

### 4.3 Backups automatizados

Configurar `/etc/cron.daily/comecyt-backup` siguiendo [`OPERATIONS.md §6.3`](OPERATIONS.md). Validar con un restore de prueba a las 24 horas.

### 4.4 Reset de admin inicial

El admin por default (`admin@comecyt.gob.mx` / `password123`) viene del seeder. **Antes de pasar el sistema a usuarios**:

1. Login con admin default
2. Activar 2FA
3. Cambiar contraseña a una fuerte (≥ 16 chars, generada con `openssl rand -base64 16`)
4. Crear admins reales para el personal autorizado
5. **Bloquear** la cuenta `admin@comecyt.gob.mx` o cambiar el email

---

## 🟣 BLOQUE 5 — POST-DEPLOY (primer mes)

### 5.1 Configurar Dependabot Auto-merge (opcional)

Para que los PRs de Dependabot de **parches de seguridad** se mergeen automáticamente:

1. Settings → General → Pull Requests → **Allow auto-merge** ☑
2. Para cada PR de Dependabot con label `security`, configurar auto-merge tras CI pass

### 5.2 Penetration Test externo

Contratar auditoría externa que valide:

- OWASP Top 10 Web
- OWASP API Top 10
- Configuración cloud/server
- Cumplimiento LFPDPPP

Plazo recomendado: dentro de los primeros 3 meses en producción.

### 5.3 Backup off-site

Los backups locales del servidor protegen contra fallos de aplicación pero no contra desastres físicos. Configurar replicación a:

- **Bucket S3 institucional** (si AWS lo permite el Estado)
- **Backup Azure Blob** (si el Estado usa Microsoft)
- **NAS institucional** de TIC (mínimo viable)

Encriptación obligatoria (SSE-KMS o equivalente).

### 5.4 Pruebas de carga

Con `k6` o `Locust`, simular 100-500 usuarios concurrentes durante la fecha de cierre de una convocatoria (suele ser el pico de tráfico).

---

## 🧹 BLOQUE 6 — LIMPIEZA LOCAL DE TU MAC (ya no es crítico)

Cosas que dejaste en tu Mac durante la sesión y que puedes limpiar con calma:

```bash
# Tag de backup que dejó git-filter-repo (ya no necesario, todo está en GitHub)
cd "/Users/fernandotorres/Desktop/comecyt/Sistema Desarrollo Tecnologico"
git tag -d pre-filter-repo-backup

# Backup del .git de apps/web (cuando confirmes que clone funciona en server)
rm -rf ~/Desktop/web-git-backup-20260609

# Historial de zsh (los PATs pueden estar ahí)
history -c && history -w

# Si no usas `gh` o `git-filter-repo`, los puedes quitar
# (yo los recomendaría dejar)
# brew uninstall gh git-filter-repo
```

---

## ✅ Checklist final consolidado

```
🔴 URGENTE (≤ 1 hora)
   ☐ Revocar PAT ghp_wzLd…3ZK
   ☐ Revocar PAT ghp_mSRz…3fxif2
   ☐ Decidir/aplicar visibilidad del repo (público/privado)
   ☐ Configurar Branch Protection en main

🟡 IMPORTANTE (≤ 1 semana)
   ☐ Crear emails institucionales (soporte@, seguridad@, etc.)
   ☐ Aviso de privacidad revisado y aprobado por Unidad de Transparencia
   ☐ Términos y condiciones revisados por área jurídica
   ☐ Acceso SSH al servidor confirmado
   ☐ Dominio público apuntando al servidor

🟢 DEPLOY (90 min)
   ☐ Instalar paquetes base (apt)
   ☐ Crear DB PostgreSQL
   ☐ Clone del repo
   ☐ Configurar .env (backend + frontend)
   ☐ Generar APP_KEY y JWT_SECRET
   ☐ bash deploy.sh
   ☐ npm ci && npm run build
   ☐ nginx + SSL (Certbot)
   ☐ Supervisor configurado
   ☐ DeployCheck en verde
   ☐ /api/health responde 200

🔵 POST-DEPLOY (semana 1)
   ☐ Monitoreo externo (UptimeRobot)
   ☐ SMTP institucional configurado
   ☐ Backups automatizados configurados
   ☐ Admin default reset (2FA + password fuerte)
   ☐ Probar los 4 roles E2E

🟣 PRIMER MES
   ☐ Pentest externo contratado
   ☐ Backup off-site configurado
   ☐ Dependabot auto-merge de security
   ☐ Pruebas de carga (k6)

🧹 LIMPIEZA LOCAL
   ☐ Borrar tag pre-filter-repo-backup
   ☐ Borrar ~/Desktop/web-git-backup-20260609
   ☐ history -c && history -w
```

---

## 📞 Si algo falla

| Problema | Dónde buscar |
|---|---|
| Error durante el deploy | [`docs/DEPLOYMENT.md`](DEPLOYMENT.md) §8 — Verificación final |
| Algo dejó de funcionar en producción | [`docs/OPERATIONS.md`](OPERATIONS.md) §8 — Troubleshooting |
| Sospecha de incidente de seguridad | [`docs/security/incident-response.md`](security/incident-response.md) |
| Cómo usar el sistema (rol-específico) | [`docs/USER_GUIDE.md`](USER_GUIDE.md) |
| Necesito un endpoint del API | [`docs/API.md`](API.md) |
| Quiero modificar el código | [`docs/DEVELOPMENT.md`](DEVELOPMENT.md) |

---

> **Estado a 2026-06-14:** Todo el código y documentación está en GitHub. Sistema 100% listo del lado técnico. Sólo falta lo que requiere acción humana en consolas externas (GitHub UI + servidor remoto).
