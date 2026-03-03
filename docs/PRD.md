# Product Requirements Document — efact

**Versión:** 1.0  
**Fecha:** 2026-03-02  
**Autor:** agt-architect  
**Fuente primaria:** Auditoría del proyecto (`docs/project-audit.md`), análisis de código fuente

---

## 1. Visión general del producto

**efact** es una plataforma SaaS de facturación electrónica multi-tenant para Perú,
diseñada para cumplir con los requisitos de la SUNAT (Superintendencia Nacional de
Aduanas y de Administración Tributaria). Permite a comercios, restaurantes, tiendas y
empresas de servicios emitir comprobantes de pago electrónicos (facturas, boletas, notas
de crédito) desde un punto de venta (POS) integrado, con sincronización automática a
la SUNAT mediante los estándares UBL 2.1 / Greenter.

El producto combina tres capacidades en un solo sistema:

1. **POS** — Caja registradora con gestión de turnos (cash sessions), medios de pago,
   inventario y listas de precios.
2. **Facturador electrónico** — Emisión, firma digital y envío de CPE (Comprobantes de
   Pago Electrónicos) a SUNAT, con reintentos automáticos y circuit breaker.
3. **Back-office** — Reportes de ventas, gestión de clientes, sucursales, usuarios y
   configuración del tenant.

---

## 2. Usuarios objetivo

| Rol | Descripción | Permisos principales |
|-----|-------------|----------------------|
| **Administrador del tenant** | Dueño o gerente del negocio. Configura el tenant, gestiona usuarios y sucursales, supervisa reportes. | Control total sobre su tenant |
| **Cajero** | Opera el POS día a día: registra ventas, emite comprobantes, abre y cierra turnos de caja. | Alta en ventas, consulta de productos |
| **Contador** | Consulta reportes fiscales, exporta registros para declaraciones SUNAT. | Solo lectura sobre ventas, documentos y reportes |

> **Nota:** El sistema es multi-sucursal por diseño. Un tenant puede tener N sucursales
> (`Branch`), cada una con sus propias cajas (`CashRegister`) y series de comprobantes
> (`VoucherSeries`). El contexto de sucursal se propaga via JWT (campo `branch_id`).

---

## 3. Funcionalidades ya implementadas

### 3.1 Arquitectura multi-tenant
- Aislamiento real a nivel de base de datos: una BD por tenant (patrón DB-per-tenant).
- `TenantConnection` resuelve la conexión desde subdominio/dominio vía `TenantListener`.
- Control plane separado (`migrations_control_plane/`) para gestión centralizada.
- Propagación de contexto en colas asíncronas con `TenantStampMiddleware`.
- Quota enforcement con distributed locks (MySQL `GET_LOCK`).

### 3.2 POS y ventas
- Registro de ventas con múltiples líneas, descuentos, impuestos (IGV 18 %) y métodos
  de pago (efectivo, tarjeta, QR, etc.).
- Gestión de turnos de caja (`CashSession` / `CashShift`) con apertura, cierre y
  cuadre.
- Flag `REQUIRE_CASH_REGISTER` por tenant para operación con o sin caja obligatoria.
- Ventas a crédito (básico) y ventas con lista de precios diferenciada.
- Consulta pública de comprobantes vía URL de recibo (`/receipt/{payload}`).

### 3.3 Facturación electrónica (SUNAT)
- Emisión de facturas (tipo 01), boletas (tipo 03) y notas de crédito (tipo 07).
- Firma digital de CPE con certificado digital usando la librería Greenter.
- Envío a SUNAT via OSE/SOL con circuit breaker (`SunatSendControlService`):
  pausa automática tras N rechazos consecutivos (configurable, default 3).
- Retry con exponential backoff (`max_attempts`, `base_seconds`, `max_seconds`).
- Recovery vía consulta CPE (`InvoiceSendRecoveryService`).
- Notificaciones multi-canal (email, WhatsApp via Bird API) cuando se pausa el envío.
- Comando cron `SendInvoiceCommand` cada 5 minutos para reintentos pendientes.
- Consulta de estado CPE en SUNAT.
- Emisión automática configurable (`AUTO_INVOICE=true`).

### 3.4 Inventario y productos
- Catálogo de productos con categorías, unidades de medida (UnitOfMeasure, SUNAT),
  lotes (StockLot) y ubicaciones de almacén (Warehouse).
- Control de stock con `ProductStock` y ajustes manuales.
- Operaciones logísticas (`LogisticOperation`).
- Listas de precios por sucursal o cliente.

### 3.5 Clientes y proveedores
- Registro de clientes con RUC/DNI y validación de formato (11 dígitos para RUC).
- Catálogo de proveedores.

### 3.6 Seguridad y autenticación
- JWT HS256 para autenticación stateless (`JWTAuthenticator`).
- Refresh tokens (`RefreshToken`).
- Rate limiting multi-capa: per-tenant (Redis) y per-endpoint para onboarding.
- Branch filter via Doctrine SQL Filter.
- Roles: `ROLE_USER`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`.

### 3.7 Observabilidad
- OpenTelemetry integrado (traces, logs, metrics) con exportación a SigNoz.
- Logging estructurado JSON con `TenantLogProcessor`.
- Audit trail completo con `AuditLogger` (procesado asíncronamente).
- Health check endpoint.
- Integración Sentry para errores.

### 3.8 Infraestructura
- Stack: PHP 8.5, Symfony 7.4, React 18, TypeScript, FrankenPHP (Caddy), MariaDB,
  Redis, Vite 6, DevExtreme (UI).
- Multi-stage Docker build (dev con Xdebug, prod con OPcache + worker mode).
- CI/CD con GitHub Actions (build, test, deploy a dev/staging/prod).
- Quality gates: PHPStan nivel 5, PHP CS Fixer, PHPMD, PHPUnit, GrumPHP pre-commit.
- Backup S3 (Wasabi).

---

## 4. Gaps críticos y requisitos de compliance

### 4.1 Seguridad — Bloqueantes para producción

| ID | Gap | Impacto |
|----|-----|---------|
| SEC-1 | `APP_SECRET` vacío — JWT firmados con string vacío; cualquier persona puede forjar tokens | **Crítico** — cualquier atacante puede impersonar cualquier usuario |
| SEC-2 | Secretos expuestos en `.env.local` (S3, GitHub, Bill API) | **Crítico** — tokens comprometidos deben rotarse antes del deploy |
| SEC-3 | Security headers ausentes en Caddyfile (HSTS, CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy) | **Alto** — expone a clickjacking, MIME sniffing, downgrade attacks |
| SEC-4 | Endpoints públicos sensibles: `/api/settings`, `/api/setup`, `/api/sales/report` con `PUBLIC_ACCESS` | **Alto** — expone configuración del tenant y reportes de ventas sin autenticación |

### 4.2 Compliance SUNAT — Bloqueantes para operar legalmente

| ID | Gap | Impacto |
|----|-----|---------|
| SUB-1 | **Resumen diario de boletas** (RC - Resumen de Comprobantes, tipo 03) — SUNAT exige envío del resumen diario antes de las 23:59 del día de emisión. Sin esto, las boletas no tienen validez fiscal. | **Crítico** — ilegalidad fiscal |
| SUB-2 | **Comunicación de baja** (RA - Resumen de Anulación) — La anulación de comprobantes ante SUNAT requiere el proceso formal de Comunicación de Baja (resumen RA), no simplemente marcar como void en la BD. | **Crítico** — anulaciones no reconocidas por SUNAT |
| SUB-3 | **Gestión de certificado digital** — Upload, almacenamiento seguro (cifrado), validación de expiración del `.pfx`/`.pem` por tenant. Sin certificado válido no se pueden firmar comprobantes. | **Crítico** — imposibilita la emisión de CPE |

### 4.3 Alta prioridad (post-launch v1.1)

| ID | Gap | Descripción |
|----|-----|-------------|
| FEAT-1 | RBAC granular | 3 roles fijos; falta configuración per-tenant de permisos por pantalla/acción |
| FEAT-2 | Reportes fiscales | Registro de ventas / compras en formato PLE SUNAT (no solo dashboards) |
| FEAT-3 | Cuentas por cobrar | Tracking de créditos, pagos parciales, estados de cuenta |
| FEAT-4 | Flujo de compras e inventario | Órdenes de compra, recepción de mercadería, ajustes de inventario |
| FEAT-5 | Términos de servicio | Obligatorio legal para SaaS en Perú |
| FEAT-6 | Exportación contable | Formatos PLE SUNAT, CONCAR, SISCONT |

### 4.4 Media prioridad (v1.1+)

| ID | Gap | Descripción |
|----|-----|-------------|
| FEAT-7 | Guías de remisión electrónicas | Obligatorio para transporte de mercadería entre almacenes |
| FEAT-8 | Multi-moneda | Ventas en USD con tipo de cambio SUNAT del día |
| FEAT-9 | Backup automatizado | Scheduling + verificación de integridad + pruebas de restore |
| FEAT-10 | Logs de actividad (UI) | El audit log existe en backend, falta UI para el admin del tenant |
| FEAT-11 | Dashboard mensajes fallidos | Monitoreo de mensajes async fallidos en Messenger |
| FEAT-12 | Disaster recovery | RTO/RPO definido, runbook de restore, procedimiento de failover |

---

## 5. Restricciones técnicas

| Restricción | Detalle |
|-------------|---------|
| **PHP 8.5** | Versión mínima. Sin downgrade. |
| **Symfony 7.4** | Framework principal. API Platform para CRUD, controllers custom para lógica compleja. |
| **React 18 + TypeScript** | Frontend SPA. DevExtreme para componentes UI enterprise. Vite 6 para build. |
| **FrankenPHP** | Servidor HTTP (Caddy embebido). Sin Nginx. Worker mode en producción. |
| **MariaDB (DB-per-tenant)** | Un schema/BD por tenant. No se puede cambiar a DB-per-schema sin refactoring mayor. |
| **Redis** | Transport de Messenger (async queues) y rate limiting. Obligatorio en producción. |
| **Multi-tenancy via subdominio** | Cada tenant accede en `{slug}.efact.pe`. El contexto se resuelve desde el `Host` header. |
| **PHPStan nivel 5** | Todos los PRs deben pasar `make check` (phpstan, phpcs, phpmd, phpunit). |
| **HS256** | Algoritmo JWT actual. Migración a RS256 está en backlog (no bloqueante para v1). |
| **Greenter** | Librería de generación de CPE (UBL 2.1). Integración existente, no reemplazable en v1. |

---

## 6. No-goals para v1

Los siguientes aspectos están **explícitamente fuera** del alcance de la versión 1.0:

- **Facturación en formato EDI / XML legacy** — solo UBL 2.1 via Greenter.
- **Módulo contable completo** — efact no es un ERP; la exportación contable (PLE/CONCAR) es v1.1.
- **App móvil nativa** — el frontend React es responsive; no hay app iOS/Android en v1.
- **Integración con SUNAT Clave SOL para presentación de PDT** — fuera de scope.
- **Multi-país** — el sistema es exclusivo para Perú (RUC, SUNAT, IGV 18 %).
- **Módulo de RRHH o nómina** — fuera de scope.
- **Migración de JWT HS256 a RS256** — evaluada en backlog post-launch.
- **Dashboard de mensajes fallidos de Messenger** — v1.1 (FEAT-11).
- **Guías de remisión electrónicas** — v1.1 (FEAT-7), aunque son obligatorias para transporte.
- **Alta disponibilidad / multi-region** — infra single-region en v1.

---

## 7. Vocabulario del dominio

| Término | Definición |
|---------|-----------|
| **Tenant** | Empresa cliente del SaaS. Tiene su propia BD aislada, subdominio, y configuración. Equivale a un RUC emisor. |
| **Branch / Sucursal** | Sede física o virtual dentro de un tenant. Un tenant puede tener N sucursales. Determina las series de comprobantes y cajas disponibles. |
| **Movimiento** | Registro legacy de venta en la tabla `movimiento`. Precede al modelo `Sale`/`SaleRecord` (v2). En migración gradual hacia `sales`. |
| **Sale / SaleRecord** | Registro de venta en el modelo v2 (`sales` table). Reemplaza a `movimiento` progresivamente. |
| **Turno / CashShift** | Turno de trabajo de un cajero. Período durante el cual la caja está operativa. (Tabla `cash_shifts`, legacy: `turno`). |
| **Sesión de caja / CashSession** | Período de apertura de una caja registradora física (`CashRegister`). Asociada a un cajero y sucursal. |
| **Comprobante de Pago Electrónico (CPE)** | Documento fiscal electrónico emitido por el emisor (factura, boleta, nota de crédito) según los estándares SUNAT. |
| **TaxDocument** | Entidad interna que representa un CPE. Incluye el XML firmado, el CDR de SUNAT y el estado de envío. |
| **SUNAT** | Superintendencia Nacional de Aduanas y de Administración Tributaria. Organismo fiscal peruano receptor de CPE. |
| **Factura (tipo 01)** | Comprobante emitido a clientes con RUC. Tiene validez tributaria para crédito fiscal. |
| **Boleta de venta (tipo 03)** | Comprobante emitido a consumidores finales (sin RUC / con DNI). Requiere resumen diario (RC) ante SUNAT. |
| **Nota de crédito (tipo 07)** | Documento que anula o modifica parcialmente una factura o boleta ya emitida. |
| **Comunicación de baja (RA)** | Resumen de anulación enviado a SUNAT para void formal de CPE. Diferente de un void interno. |
| **Resumen diario (RC)** | Archivo resumen de boletas del día enviado a SUNAT. Obligatorio para boletas (tipo 03). |
| **RUC** | Registro Único de Contribuyentes. Identificador fiscal de 11 dígitos de una empresa peruana. |
| **OSE** | Operador de Servicios Electrónicos. Intermediario autorizado por SUNAT (alternativa al SOL directo). |
| **Serie** | Código alfanumérico que identifica el punto de emisión de un comprobante (ej. `F001` para facturas, `B001` para boletas). |
| **VoucherSeries** | Entidad que agrupa series por tipo de comprobante y sucursal, con correlativo auto-incremental. |
| **CDR** | Constancia de Recepción. Respuesta de SUNAT/OSE que indica si el CPE fue aceptado o rechazado. |
| **IGV** | Impuesto General a las Ventas. Tasa del 18 % en Perú, equivalente al IVA. |
| **Certificado digital** | Archivo `.pfx` o `.pem` con clave privada y certificado X.509 usado para firmar los XML de los CPE. Emitido por una entidad certificadora autorizada por INDECOPI. |
| **Control plane** | Capa de gestión centralizada del SaaS (onboarding, provisioning, quotas). Tiene su propia BD separada. |
| **GrumPHP** | Herramienta de pre-commit hooks que ejecuta quality gates antes de cada commit. |

---

## 8. Roadmap de lanzamiento

```
Semana 1 — Seguridad (SLICE-005 … SLICE-008)
  ✓ SEC-1: APP_SECRET seguro
  ✓ SEC-2: Rotación de secretos expuestos
  ✓ SEC-3: Security headers en Caddyfile
  ✓ SEC-4: Proteger endpoints públicos

Semana 2–3 — Compliance SUNAT (SLICE-009 … SLICE-011)
  ✓ SUB-3: Gestión de certificado digital
  ✓ SUB-1: Resumen diario de boletas
  ✓ SUB-2: Comunicación de baja

Semana 4 — Hardening (SLICE-012 …)
  ✓ Tests de integración críticos
  ✓ Disaster recovery básico
  ✓ Términos de servicio
```

---

*Este documento es la fuente de verdad para la planificación de slices. Cualquier cambio
de alcance debe reflejarse aquí antes de crear o modificar un slice spec.*
