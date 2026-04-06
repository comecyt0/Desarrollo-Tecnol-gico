# COMECYT - Requisitos del Servidor (Para Solicitud)

## 🖥️ ESPECIFICACIONES DEL SERVIDOR

### Sistema Operativo
- [ ] Windows Server 2019 o superior
- [ ] Últimas actualizaciones de Windows

### Procesador
- [ ] Mínimo: 2 cores @ 2.0 GHz
- [ ] Recomendado: 4-8 cores @ 2.4+ GHz

### Memoria RAM
- [ ] Mínimo: 4 GB
- [ ] Recomendado: 16 GB

### Almacenamiento
- [ ] Mínimo: 50 GB SSD
- [ ] Recomendado: 100+ GB SSD con RAID 1

### Conectividad
- [ ] IP fija (no DHCP)
- [ ] Ancho de banda: 10 Mbps (mínimo), 100+ Mbps (producción)
- [ ] Acceso a internet sin restricciones

### Acceso
- [ ] Acceso RDP (Remote Desktop Protocol) habilitado
- [ ] Credenciales Admin disponibles
- [ ] Usuario con permisos administrativos

---

## 🔌 PUERTOS REQUERIDOS

### Puertos de Ingreso (Internet)
| Puerto | Protocolo | Servicio | Estado |
|--------|-----------|----------|--------|
| **80** | HTTP | Redirección a HTTPS | ✅ ABIERTO |
| **443** | HTTPS | Frontend + API | ✅ ABIERTO |

### Puertos Internos (Localhost - Cerrados a Internet)
| Puerto | Protocolo | Servicio | Estado |
|--------|-----------|----------|--------|
| **3000** | HTTP | Next.js Frontend | ⚠️ Solo localhost |
| **8000** | HTTP | Laravel API | ⚠️ Solo localhost |
| **5432** | TCP | PostgreSQL | ⚠️ Solo localhost |
| **9000** | TCP | PHP-FPM (opcional) | ⚠️ Solo localhost |

### Puertos Dinámicos
| Rango | Protocolo | Uso |
|-------|-----------|-----|
| **49152-65535** | TCP/UDP | Conexiones dinámicas | ✅ PERMITIR |

---

## 📦 SOFTWARE REQUERIDO

### Base de Datos
- [ ] PostgreSQL 14.x, 15.x o 16.x
- [ ] Puerto: 5432
- [ ] Usuario: `comecyt`
- [ ] Base de datos: `comecyt_production`

### Servidor Web
- [ ] IIS 10.0+ (Windows-native) **O**
- [ ] Nginx para Windows (recomendado)

### Lenguajes de Programación
- [ ] PHP 8.3.x (Non-Thread-Safe para IIS)
- [ ] Node.js 18.x LTS o 20.x LTS (recomendado)

### Gestores de Dependencias
- [ ] Composer 2.6+
- [ ] npm 9+ (incluido con Node.js)

### Control de Versiones
- [ ] Git 2.4+

### Opcional pero Recomendado
- [ ] pgAdmin 4 (administración PostgreSQL)
- [ ] Nginx (reverse proxy)
- [ ] PM2 (gestor de procesos Node.js)

---

## 🔧 CONFIGURACIÓN DE FIREWALL

### Inbound Rules (Entrada desde Internet)
```
✅ PERMITIR 0.0.0.0/0 → Puerto 80 TCP
✅ PERMITIR 0.0.0.0/0 → Puerto 443 TCP
❌ BLOQUEAR 0.0.0.0/0 → Puerto 8000 TCP (API)
❌ BLOQUEAR 0.0.0.0/0 → Puerto 3000 TCP (Frontend)
❌ BLOQUEAR 0.0.0.0/0 → Puerto 5432 TCP (Database)
```

### Outbound Rules (Salida a Internet)
```
✅ PERMITIR: HTTPS (443) - Actualizaciones Windows/npm
✅ PERMITIR: DNS (53) - Resolución de dominios
✅ PERMITIR: HTTP (80) - Descargas
```

---

## 📊 EXTENSIONES PHP REQUERIDAS

```
✅ php-pgsql         (PostgreSQL connection)
✅ php-curl          (HTTP requests)
✅ php-json          (JSON parsing)
✅ php-mbstring      (String functions)
✅ php-tokenizer     (Code parsing)
✅ php-xml           (XML parsing)
✅ php-fileinfo      (MIME type detection)
✅ php-zip           (ZIP compression)
✅ php-gd            (Image/PDF generation)
✅ php-opcache       (Performance optimization)
```

---

## 🔐 CERTIFICADO SSL/TLS

- [ ] Certificado SSL/TLS válido
- [ ] Para dominio: `[tu-dominio.com.mx]`
- [ ] Opciones: Let's Encrypt (gratuito) o certificado comprado
- [ ] Renovación automática configurada

---

## 💾 ALMACENAMIENTO ADICIONAL

```
📁 Documentos uploadados:
   Ruta: C:\storage\documentos
   Espacio estimado: 50-100 GB (depende de uso)

📁 Backups diarios:
   Ruta: C:\backup
   Espacio estimado: 20-50 GB (últimos 30 días)

📁 Logs de aplicación:
   Ruta: C:\apps\comecyt-system\storage\logs
   Espacio estimado: 5-10 GB
```

---

## 🌐 REQUISITOS DE RED

- [ ] DNS configurado para dominio
- [ ] SPF, DKIM, DMARC configurados (para email)
- [ ] NTP (Network Time Protocol) activo
- [ ] Resolución DNS con latencia < 50ms

---

## 📋 SERVICIOS WINDOWS REQUERIDOS

```
✅ IIS (Internet Information Services)
✅ Windows Update
✅ Task Scheduler (para backups automáticos)
✅ Event Viewer (para logs)
✅ Performance Monitor (opcional)
```

---

## ⚡ REQUISITOS DE RENDIMIENTO

- [ ] Velocidad de disco: 400+ IOPS (SSD)
- [ ] Latencia de red: < 50ms
- [ ] Disponibilidad: 99.5% uptime mínimo
- [ ] Backup diario: Sí, automatizado

---

## 🔒 SEGURIDAD

- [ ] Antivirus Windows Defender activo
- [ ] Windows Firewall configurado
- [ ] Actualizaciones automáticas habilitadas
- [ ] Contraseñas complejas (mínimo 16 caracteres)
- [ ] SSH/RDP con autenticación de clave (opcional)
- [ ] Acceso remoto VPN (recomendado)

---

## 📞 ACCESO AL SERVIDOR

### Métodos de Acceso
- [ ] RDP (Remote Desktop Protocol) - Puerto 3389
- [ ] SSH (opcional, si Nginx en WSL)
- [ ] Console físico (si data center)
- [ ] KVM/IPMI (si servidor virtual)

### Credenciales
- [ ] Usuario Admin: `[nombre_usuario]`
- [ ] Contraseña: `[contraseña_compleja]`
- [ ] Correo de contacto del administrador: `[email]`

---

## 📧 CONFIGURACIÓN EMAIL (Opcional)

Si quieres notificaciones automáticas:

- [ ] Servidor SMTP: `[smtp.proveedor.mx]`
- [ ] Puerto: `587` o `465`
- [ ] Usuario SMTP: `[email@dominio.mx]`
- [ ] Contraseña SMTP: `[contraseña]`
- [ ] From: `noreply@comecyt.gob.mx`

---

## ✅ VERIFICACIÓN PRE-DEPLOY

```
☐ Ping a servidor responde < 50ms
☐ Puerto 22 (SSH) abierto OR Port 3389 (RDP) abierto
☐ PostgreSQL instalado y corriendo (puerto 5432)
☐ PHP 8.3 con extensiones instalado
☐ Node.js 20 LTS instalado
☐ Composer instalado
☐ Git instalado
☐ Certificado SSL válido
☐ Firewall configurado (80/443 abiertos)
☐ 50+ GB de espacio disponible
☐ IP fija asignada
```

---

## 📋 RESUMEN PARA SOLICITUD

**Copiar y pegar en solicitud de servidor:**

```
COMECYT Sistema - Requisitos del Servidor:

ESPECIFICACIONES MÍNIMAS:
- Windows Server 2019+
- 4 GB RAM, 50 GB SSD
- 2 cores @ 2.0 GHz
- IP fija

ESPECIFICACIONES RECOMENDADAS (PRODUCCIÓN):
- Windows Server 2022
- 16 GB RAM, 100 GB SSD RAID 1
- 4-8 cores @ 2.4+ GHz
- IP fija + backup automático

PUERTOS REQUERIDOS:
- Entrada internet: 80 (HTTP), 443 (HTTPS)
- Internos: 3000, 8000, 5432 (solo localhost)

SOFTWARE:
- PostgreSQL 14-16
- PHP 8.3.x (NTS)
- Node.js 18-20 LTS
- Nginx o IIS
- Composer 2.6+
- Git 2.4+

CERTIFICADO: SSL/TLS para [tu-dominio.com.mx]

EXTRAS:
- Antivirus Windows Defender
- Firewall configurado
- Backups automáticos diarios
- Acceso RDP/SSH
```

---

**Versión:** 1.0
**Fecha:** 06 Abril 2026
**Para:** Solicitud de servidor Windows Server
