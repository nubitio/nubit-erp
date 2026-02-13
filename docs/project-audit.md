# Auditoría del Proyecto efact

**Fecha:** 2026-02-13
**Alcance:** Análisis completo de arquitectura, seguridad, código y preparación para producción

---

## 1. Lo que está BIEN

### 1.1 Arquitectura multi-tenant sólida
- Base de datos separada por tenant con `TenantConnection` — aislamiento real a nivel DB
- Control plane separado (`migrations_control_plane/`) para gestión centralizada
- `TenantListener` con prioridad 1024 resuelve tenant desde subdomain/domain
- Context propagation a través de Messenger con `TenantStampMiddleware`
- Quota enforcement con `QuotaEnforcementListener` usando distributed locks (`GET_LOCK`)

### 1.2 Stack tecnológico moderno y coherente
- PHP 8.5, Symfony 7.4, React 18, TypeScript, FrankenPHP
- API Platform para operaciones CRUD, controllers custom para lógica compleja
- DevExtreme para UI profesional de nivel enterprise
- Vite 6 para build frontend con HMR en desarrollo

### 1.3 Observabilidad
- OpenTelemetry integrado (traces, logs, metrics) con exportación a SigNoz
- Logging estructurado en JSON (`monolog.formatter.json`) con `TenantLogProcessor`
- Audit trail completo con `AuditLogger` procesado de forma asíncrona
- Health check endpoint con verificación de base de datos

### 1.4 Integración SUNAT robusta
- Circuit breaker (`SunatSendControlService`) que pausa envíos tras rechazos consecutivos (threshold configurable, default 3)
- Retry con exponential backoff configurable (max_attempts, base_seconds, max_seconds)
- Recovery via consulta CPE como fallback (`InvoiceSendRecoveryService`)
- Notificaciones multi-canal (email, WhatsApp via Bird) cuando se pausa el envío
- Comando cron `SendInvoiceCommand` cada 5 minutos para reintentos

### 1.5 Seguridad en queries
- Todas las consultas DBAL usan parámetros — no hay inyección SQL
- No se usa `$_POST`, `$_GET`, `$_REQUEST` directamente — todo pasa por el Request object de Symfony
- Validación con Symfony Constraints en entidades

### 1.6 Quality gates en CI
- PHPStan nivel 5, PHP CS Fixer, PHPMD, PHPUnit, GrumPHP pre-commit hooks
- GitHub Actions para build, test y deploy (dev/staging/prod)
- Dockerfile multi-stage con targets separados para dev/staging/prod

### 1.7 Rate limiting multi-capa
- Per-tenant via Redis (300 req/60s configurable con `TENANT_RATE_LIMIT` / `TENANT_RATE_WINDOW`)
- Per-endpoint para onboarding (registro: 5/hora, verificación: 10/15min, creación: 3/día)
- Headers estándar `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After`

### 1.8 Infraestructura Docker
- Multi-stage build optimizado (dev con Xdebug, prod con OPcache y worker mode)
- Health checks configurados en Dockerfile y docker-compose
- Log rotation en producción (`max-size: 10m`, `max-file: 3`)
- PHP production config: `expose_php = Off`, `display_errors = Off`

---

## 2. Lo que está ACEPTABLE

### 2.1 Cobertura de tests
- Existen tests para: provisioning, quota, retry de invoices, calculadora, seguridad, subdomain validation
- **Falta:** Tests para `SaleProcessor`, controllers API, flujos completos de venta, tests de integración para multi-tenancy

### 2.2 Organización del código
- `src/EInvoicing/` usa DDD correctamente (Domain/Infrastructure/Application)
- `src/Core/` tiene buena separación (Audit, Cache, Context, Security, etc.)
- **Inconsistencia:** `src/` mezcla entidades flat con módulos verticales, repositories flat con state providers por feature. No es grave pero genera confusión sobre dónde poner código nuevo

### 2.3 Branch filter (multi-sucursal)
- Funciona via Doctrine SQL Filter + branch ID en JWT
- Cambio de sucursal genera nuevo JWT con `AuthSelectBranchController`
- **Riesgo:** Depende de que todas las entidades relevantes tengan relación `branch` — si se agrega una entidad sin ella, el filtro no aplica silenciosamente

### 2.4 DTOs
- Buenos DTOs para Auth, Sale, CashRegister, Invoice, Assistant
- **Falta:** Validación consistente en todos los DTOs, algunos no tienen constraints

### 2.5 Manejo de errores
- Hierarchy de excepciones: `ServiceException`, `ValidationException`, `QuotaExceededException`, `NotFoundException`, `SaleException`
- `ExceptionListener` captura todo y devuelve JSON con status code apropiado
- **Falta:** Error codes estandarizados (ej. `QUOTA_EXCEEDED`, `INVOICE_ALREADY_SENT`) para que el frontend pueda reaccionar programáticamente

### 2.6 Async processing
- Symfony Messenger con Redis transport para: envío de facturas, provisioning, audit logs
- Failed transport configurado para reintentos
- **Falta:** Dashboard o herramienta para monitorear mensajes fallidos

---

## 3. Lo que está MAL

### 3.1 Endpoints públicos sensibles

En `config/packages/security.yaml`:

```yaml
- { path: ^/api/settings, roles: PUBLIC_ACCESS }     # Expone configuración del tenant
- { path: ^/api/setup, roles: PUBLIC_ACCESS }         # Permite ejecutar setup sin auth
- { path: ^/api/sales/report, roles: PUBLIC_ACCESS }  # Reportes de ventas sin auth
```

**Acción:** Restringir a `ROLE_USER` o `ROLE_ADMIN`. Si `/api/setup` necesita ser público para el primer setup, agregar un guard que lo bloquee si ya fue ejecutado.

### 3.2 Security headers ausentes

El Caddyfile (`docker/frankenphp/Caddyfile`) no incluye headers de seguridad obligatorios:

```
# FALTANTES:
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

### 3.3 Health endpoint sin protección

`/health` expone estado del sistema (database ok/degraded) sin autenticación. Un atacante puede monitorear disponibilidad para timing attacks.

**Acción:** Agregar rate limiting o restringir por IP, o devolver solo status code sin body para requests no autenticados.

### 3.4 JWT con HS256

`JWTManager` usa HS256 (simétrico) con `APP_SECRET`. Si el secret se filtra, cualquiera puede generar tokens válidos. Para un SaaS multi-tenant, RS256 con par de claves asimétricas es más seguro.

### 3.5 Composer como superuser en Docker

```dockerfile
ENV COMPOSER_ALLOW_SUPERUSER=1
```

Riesgo de escalación de privilegios si hay vulnerabilidad en alguna dependencia durante el build.

### 3.6 Migración inicial monolítica

`migrations/Version20251205023010_initial.php` tiene ~24,000 líneas. Imposible de debuggear, hacer rollback parcial, o entender qué cambió.

### 3.7 Admin panel con HTTP Basic Auth

```yaml
admin:
  pattern: ^/admin
  http_basic:
    realm: 'Admin Panel'
```

HTTP Basic Auth transmite credenciales en cada request (base64, no cifrado). En producción debe usarse al menos HTTPS obligatorio + considerar migrar a un sistema de auth más robusto para el control plane.

---

## 4. Lo que está SUPER MAL (Crítico)

### 4.1 Secretos hardcodeados en .env.local

```
S3_ACCESS_SECRET=WUe2zZes5M5jzLKlEcokIgkAu9lus5IzgzMc4In4
GITHUB_TOKEN=github_pat_11AAHRYAY0r0bhT2nmsHaO_...
BILL_API_TOKEN=123456
```

Aunque `.env.local` está en `.gitignore`, estos valores existieron en el entorno de desarrollo. **Rotar inmediatamente** todos los tokens. Usar un secrets manager (Symfony Secrets, HashiCorp Vault, o variables de entorno del CI/CD provider).

### 4.2 APP_SECRET vacío

```
APP_SECRET=
```

Los JWT se firman con string vacío. **Cualquiera puede forjar tokens de autenticación**. Esta es la vulnerabilidad más crítica del proyecto.

**Acción inmediata:** Generar un secret de al menos 32 bytes random:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### 4.3 Credenciales de DB root en plaintext

```
PROVISIONING_DATABASE_URL="mysql://root:root@database:3306..."
DATABASE_URL="mysql://demo:Admin.3000@database:3306/demo..."
```

El provisioning usa `root:root`. Las credenciales de tenant usan passwords débiles. En producción:
- Usar credenciales únicas y fuertes por entorno
- No usar root para provisioning — crear un usuario con solo `CREATE DATABASE`, `CREATE USER`, `GRANT`
- Usar secrets management

---

## 5. Features faltantes para v1 en producción

### 5.1 Bloqueantes (sin estos NO se puede ir a producción)

| # | Feature | Detalle |
|---|---------|---------|
| 1 | **APP_SECRET seguro** | Generar secret robusto de 32+ bytes y configurar en cada entorno |
| 2 | **Rotación de secretos expuestos** | Rotar S3, GitHub, Bill API tokens. Usar secrets manager |
| 3 | **Security headers** | Agregar HSTS, CSP, X-Frame-Options, X-Content-Type-Options al Caddyfile |
| 4 | **Proteger endpoints públicos** | `/api/settings`, `/api/setup`, `/api/sales/report` deben requerir autenticación |
| 5 | **Resumen diario de boletas** | SUNAT requiere envío de resumen diario para boletas (tipo 03). Sin esto, las boletas no tienen validez fiscal |
| 6 | **Comunicación de baja** | Proceso formal de anulación ante SUNAT (RA - Resumen de Anulación). Diferente al void actual |
| 7 | **Gestión de certificado digital** | Upload, almacenamiento seguro, validación de expiración del certificado .pfx/.pem por tenant. Sin esto no se pueden firmar comprobantes |

### 5.2 Alta prioridad (necesarios para primera iteración post-launch)

| # | Feature | Detalle |
|---|---------|---------|
| 8 | **Permisos granulares (RBAC)** | Solo hay 3 roles fijos (admin, user, cashier). Falta configuración por tenant de qué pantallas y acciones puede hacer cada rol |
| 9 | **Reportes fiscales** | Registro de ventas, registro de compras, libro diario. Los reportes actuales son dashboards, no reportes fiscales SUNAT |
| 10 | **Cuentas por cobrar** | Tracking de ventas a crédito, pagos parciales, estados de cuenta por cliente |
| 11 | **Flujo de compras e inventario** | Órdenes de compra, recepción de mercadería, ajustes de inventario, toma de inventario físico |
| 12 | **Términos de servicio** | Obligatorio legal para SaaS en Perú. Política de privacidad, condiciones de uso |
| 13 | **Exportación contable** | Exportar a formatos PLE SUNAT o software contable peruano (CONCAR, SISCONT) |

### 5.3 Media prioridad (pueden esperar a v1.1)

| # | Feature | Detalle |
|---|---------|---------|
| 14 | **Guías de remisión electrónicas** | Obligatorio para transporte de mercadería entre sucursales/almacenes |
| 15 | **Multi-moneda** | Soporte para ventas en USD con tipo de cambio SUNAT del día |
| 16 | **Backup automatizado** | El servicio existe pero falta scheduling, verificación de integridad, pruebas de restore |
| 17 | **Logs de actividad (UI)** | El audit log existe en backend pero no hay UI para que el admin del tenant vea quién hizo qué |
| 18 | **Monitoreo de mensajes fallidos** | Dashboard para ver mensajes async que fallaron en Messenger |
| 19 | **Disaster recovery documentado** | RTO/RPO definido, runbook de restore, procedimiento de failover |

---

## 6. Resumen ejecutivo

### Veredicto general

El proyecto tiene una **base arquitectónica sólida** para un SaaS multi-tenant. La separación de bases de datos por tenant, el circuit breaker de SUNAT, la observabilidad con OpenTelemetry, y el rate limiting son decisiones de diseño maduras.

Los problemas críticos son todos de **gestión de secretos y configuración de seguridad** — no de arquitectura. Son corregibles en días, no semanas.

Las features faltantes más importantes son las de **compliance SUNAT** (resumen diario, comunicación de baja, certificado digital). Sin ellas, el sistema no puede operar legalmente como facturador electrónico.

### Prioridades para producción

```
Semana 1: Seguridad
  - Fijar APP_SECRET
  - Rotar secretos
  - Security headers
  - Proteger endpoints

Semana 2-3: SUNAT compliance
  - Resumen diario de boletas
  - Comunicación de baja
  - Gestión de certificado digital

Semana 4: Hardening
  - Tests de integración críticos
  - Disaster recovery básico
  - Términos de servicio
```
