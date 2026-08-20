# PRD — eFact SaaS POS + Facturacion Electronica

**Version:** 2026-05-14
**Estado:** activo
**Producto:** eFact POS, facturacion electronica SUNAT y control plane SaaS
**Mercado objetivo:** Peru

---

## 1. Vision

eFact es una plataforma SaaS multi-tenant para pequenas y medianas empresas peruanas que necesitan vender, controlar inventario, operar caja y emitir comprobantes electronicos SUNAT desde un mismo flujo operativo.

El producto combina tres superficies:

| Superficie | Repo | Proposito |
|---|---|---|
| Backend + Web POS | `efact` | Monolito Symfony/API Platform multi-tenant con frontend React |
| Android POS | `efact-app` | Terminal POS tablet/offline con impresion local |
| Marketing site | `efact-site` | Sitio publico, catalogo de planes y captacion |

La promesa principal es reducir el tiempo desde registro hasta primera venta facturada, manteniendo aislamiento por tenant, cumplimiento SUNAT y continuidad operativa ante cortes de internet.

---

## 2. Problema

Las pymes peruanas deben emitir boletas/facturas validas ante SUNAT, pero suelen operar con herramientas fragmentadas:

- POS instalables o antiguos sin buena experiencia touch.
- Facturacion electronica separada del punto de venta.
- Inventario y caja controlados manualmente.
- Poca visibilidad de comprobantes pendientes/rechazados.
- Dependencia de conectividad para vender o imprimir.
- Onboarding tecnico lento: certificados, series, sucursales, almacenes, usuarios y credenciales SUNAT.

El resultado es cola en caja, errores fiscales, stock poco confiable y alto costo de soporte por cliente.

---

## 3. Objetivos

1. **Tiempo a primera factura <= 10 minutos:** un tenant nuevo debe poder registrarse, configurar empresa/sucursal/series y emitir el primer comprobante sin intervencion manual.
2. **Aceptacion SUNAT >= 99.5 %:** comprobantes enviados deben quedar aceptados o recuperados via consulta CDR dentro de 24 horas por ciclo de facturacion.
3. **Operacion POS offline:** el cajero debe poder crear ventas e imprimir recibos aun sin internet, con outbox sincronizable y sin perdida de datos.
4. **Aislamiento por tenant:** cada empresa opera en su propia base MariaDB; el control plane administra tenants, dominios, planes, cuotas, backups y certificados desde una base separada.

---

## 4. Usuarios

| Persona | Necesidad principal | Superficie |
|---|---|---|
| Cajero | Vender rapido, cobrar, imprimir y emitir comprobante sin pasos fiscales extras | Web POS / Android POS |
| Supervisor o dueno | Revisar ventas, caja, inventario, series, comprobantes y reportes | Web admin tenant |
| Operador eFact | Provisionar tenants, revisar planes, certificados, cuotas, backups y webhooks | `/admin` control plane |
| Prospecto | Entender planes, beneficios y registrarse | `efact-site` |

---

## 5. Estado Actual Del Producto

### Backend `efact`

- PHP 8.5, Symfony 7.4, API Platform 4.1, Doctrine ORM/Migrations y FrankenPHP.
- Arquitectura modular por bounded contexts de producto: `Sales`, `Billing`, `Inventory`, `Purchasing`, `CashRegister`, `Catalog`, `Contacts`, `Organization`, `Identity`, `ControlPlane`, `Reporting`, `Settings`, `Setup`, `Media`, `AI` y `Efact`.
- Componentes reutilizables ya extraidos como dependencias Composer `nubitio/platform`, `nubitio/api-platform` y `nubitio/admin-bundle`; `src/Core` esta retirado y conserva solo su README historico.
- MariaDB database-per-tenant y base separada de control plane.
- Redis para cache/colas Messenger y Mercure para eventos realtime.
- Observabilidad OpenTelemetry con instrumentacion Symfony, Doctrine y Monolog.
- S3/Flysystem para adjuntos, CDR/XML, backups y media tenant.

### Frontend Web

- React 19, TypeScript strict, Vite 6, TailwindCSS 4.
- PWA con Workbox, Dexie/IndexedDB para modo offline y outbox.
- React Query para cache de servidor, cliente HTTP interno sobre `fetch` y Mercure SSE para realtime.
- Modulos actuales: venta, facturacion, compras, inventario/movimientos, transferencias, clientes, caja, configuracion, admin, asistente AI y notificaciones.

### Android POS

- Kotlin, Jetpack Compose, Room, Retrofit, Koin, Coroutines/Flow.
- Flujo POS equivalente: login, seleccion de sucursal, productos, carrito, cliente, venta, caja e impresion ESC/POS Bluetooth.
- El estado del repo indica documentacion de slices y QA; debe tratarse como cliente de la API eFact, no como fuente de verdad fiscal.

---

## 6. Funcionalidades Principales

### 6.1 Autenticacion y Sesion

- JWT propio con cookies HttpOnly y refresh tokens.
- Seleccion de sucursal posterior al login; el contexto de sucursal queda en el token.
- Roles tenant (`ROLE_USER`, `ROLE_CASHIER`, `ROLE_ADMIN`) y roles de plataforma/control plane.
- Limpieza programada de tokens via Symfony Scheduler.

### 6.2 POS y Ventas

- Busqueda de productos por nombre, codigo, SKU o codigo de barras.
- Carrito con cantidades, precios, descuentos por item y descuento global.
- Metodo de pago efectivo, tarjeta o mixto; calculo de vuelto.
- Creacion de venta con descuento de stock y pagos asociados.
- Generacion de comprobante desde venta y anulacion de venta.
- Impresion por ESC/POS, QZ Tray, WebUSB o agente local segun cliente.
- URL publica de recibo con descarga de ticket, PDF, XML y CDR.

### 6.3 Facturacion Electronica SUNAT

Documentos soportados:

| Codigo | Documento |
|---|---|
| 01 | Factura electronica |
| 03 | Boleta electronica |
| 07 | Nota de credito |
| 08 | Nota de debito |
| RA | Resumen diario |
| RC | Comunicacion de baja |

Flujo actual:

1. `POST /api/sales/{id}/invoice` crea `TaxDocument` y lineas desde la venta.
2. `POST /api/tax-documents/{id}/validate` genera y firma UBL 2.1 con Greenter.
3. `POST /api/tax-documents/{id}/send` despacha envio asincrono a SUNAT.
4. `GET /api/tax-documents/{id}/consult` consulta CDR y reconcilia estado.
5. `POST /api/tax-documents/{id}/email` envia comprobante a cliente.

Mecanismos de seguridad fiscal:

- Circuit breaker por racha de rechazos.
- Retry con backoff exponencial.
- Eventos `TaxDocumentEvent` para auditoria.
- Almacenamiento de CDR/XML en storage tenant.
- Calculo monetario exacto con `brick/math` y politica de descuentos secuencial documentada en [ADR-014](adr/ADR-014-sequential-discount-strategy.md).

### 6.4 Inventario y Compras

- Productos con UUID, SKU, codigo de barras, categoria, unidad, precio, costo, comportamiento de inventario y trazabilidad de lote.
- Almacenes por sucursal.
- Ledger unificado de `stock_movements` y read model `stock_items`.
- Idempotencia por clave SHA-256 para evitar movimientos duplicados.
- Compras (`goods_receipts`) con sincronizacion offline, estado y anulacion.
- Transferencias, ajustes, ingresos y salidas.
- Productos compuestos con explosion BOM via `BomExplosionService`.
- Lotes con FIFO/FEFO via `LotDeductionService` usando aritmetica decimal `bcmath`.

### 6.5 Caja

- Cajas por sucursal.
- Sesiones de caja `OPEN`, `RECONCILING`, `CLOSED`.
- Monto inicial, esperado, contado y diferencia.
- Movimientos de ingreso/egreso categorizados.

### 6.6 Clientes y Proveedores

- Clientes con DNI/RUC, razon social, direccion y lista de precios.
- Consulta DNI/RUC via endpoints `GET /api/consults/dni/{id}` y `GET /api/consults/ruc/{id}`.
- Proveedores para compras e inventario.

### 6.7 Reportes, BI y Notificaciones

- Dashboard con ventas diarias, productos top, ventas por hora, alertas de stock y comprobantes pendientes.
- Exportes Excel de ventas con PHPSpreadsheet.
- Resumenes diarios de ventas/compras reconstruibles por comandos programados.
- Notificaciones in-app, incluyendo alertas de stock generadas periodicamente.

### 6.8 AI y MCP

- Endpoint `POST /api/assistant` con herramientas para ventas, productos, caja, documentacion, stock, SUNAT y clientes.
- Indice de conocimiento reconstruido diariamente.
- Endpoint MCP en `/mcp` via `symfony/mcp-bundle` para exponer herramientas a clientes compatibles.

### 6.9 Control Plane SaaS

- Registro self-service: `/register`, verificacion email y wizard `/register/new-tenant`.
- Provisioning automatico de base tenant, migraciones, admin inicial, sucursal, almacen, series, catalogos y metodos de pago.
- Admin `/admin` con Http Basic separado del tenant auth.
- Planes, cuotas y feature gates por plan/tenant.
- Overrides temporales auditables y limpieza programada.
- Webhooks PayPal/MercadoPago con semantica idempotente.
- Certificados SUNAT con lifecycle en control plane y sincronizacion recuperable con lycet.
- Backups, binlogs, pruning y descarga protegida por `ROLE_SUPER_ADMIN`.

---

## 7. Arquitectura

### Principios

- **Database-per-tenant:** no introducir `tenant_id` en tablas tenant; el aislamiento se resuelve por conexion.
- **Host como tenant resolver:** `TenantListener` resuelve tenant por dominio/subdominio; rutas publicas/control plane son excepciones explicitas.
- **API-first:** contratos API Platform antes de UI.
- **Offline-first en POS:** ventas y catalogo deben tolerar desconexion mediante IndexedDB/Room y outbox.
- **Asincrono para trabajo pesado:** facturacion, backups, provisioning, auditoria y exportes no deben bloquear requests.
- **Observabilidad obligatoria:** operaciones criticas deben emitir logs/spans rastreables.
- **Platform boundary:** la frontera portable vive en paquetes `nubitio/*`; efact solo enlaza contratos `Nubit\Platform\*` hacia implementaciones `ControlPlane`/tenant en `config/services.yaml`. Ver `docs/architecture/platform-boundary-map.md`.

### Decisiones vigentes

> **Corregido 2026-08-19:** los números ADR-001 a ADR-004 y ADR-009 en esta lista nunca existieron como archivos y colisionaban con los ADR-001..006 reales de `docs/adr/` (temas distintos). Se reconstruyeron como documentos formales en `docs/adr/ADR-007` a `ADR-015`, renumerados para no chocar con los existentes. Ver [ADR-006](adr/ADR-006-pragmatic-per-context-layout.md) para el contexto de por qué había numeraciones duplicadas.

- [ADR-007](adr/ADR-007-database-per-tenant-split-control-plane-connection.md): database-per-tenant + conexión de control plane separada.
- [ADR-008](adr/ADR-008-custom-stateless-jwt-authentication.md): JWT custom (no LexikJWT).
- [ADR-009](adr/ADR-009-frankenphp-runtime.md): FrankenPHP runtime.
- [ADR-010](adr/ADR-010-inventory-ledger-read-model-cutover.md): inventory ledger/read model y cutover de servicio de inventario (cutover incompleto — ver el ADR).
- [ADR-011](adr/ADR-011-sunat-billing-greenter-model-lycet-submission.md): facturación SUNAT — Greenter solo como modelo de datos, envío real vía Lycet/Bill.
- [ADR-012](adr/ADR-012-control-plane-hardening-and-boundary.md): hardening y frontera de ControlPlane (con gaps documentados: sin CSP, guard de frontera fuera de CI).
- [ADR-013](adr/ADR-013-feature-gating-entitlements-vs-capabilities.md): feature gates por plan (entitlements) vs. capabilities por vertical.
- [ADR-014](adr/ADR-014-sequential-discount-strategy.md): estrategia de descuentos secuencial.
- [ADR-015](adr/ADR-015-mixed-scheduler-model.md): Scheduler operativo como modelo mixto provider + atributos (confirmado como deriva orgánica, no decisión deliberada).

---

## 8. API Surface Prioritaria

| Area | Endpoints |
|---|---|
| Auth | `POST /api/auth/login`, `/refresh`, `/logout`, `/select-branch` |
| CRUD core | `/api/products`, `/api/sales`, `/api/tax-documents`, `/api/purchases`, `/api/customers`, `/api/users`, `/api/branches`, `/api/warehouses`, `/api/cash-sessions`, `/api/voucher-series`, `/api/stock-lots` |
| Operaciones venta/factura | `/api/sales/{id}/invoice`, `/api/sales/{id}/void`, `/api/sales/{id}/print-invoice`, `/api/tax-documents/{id}/validate`, `/send`, `/consult`, `/email` |
| Consultas externas | `/api/consults/ruc/{id}`, `/api/consults/dni/{id}` |
| BI/exportes | `/api/bi/{type}`, `/api/sales/report` |
| Setup | `GET /api/setup`, `POST /api/setup` |
| AI/MCP | `POST /api/assistant`, `/mcp` |
| Publico | `/receipt/{id}`, `/receipt/{id}/ticket`, `/pdf`, `/xml`, `/cdr`, `/sale/consult` |
| Webhooks | `/webhook/paypal`, `/webhook/mercadopago` |

---

## 9. Metricas De Exito

- `tenant_time_to_first_invoice_minutes` p95 <= 10.
- `sunat_acceptance_rate_24h` >= 99.5 % por tenant/ciclo.
- `offline_sale_sync_success_rate` >= 99.9 %.
- `invoice_rejection_streak_breaker_incidents` revisados con runbook.
- `provisioning_success_rate` >= 99 % sin intervencion manual.
- `backup_success_rate` >= 99 % por tenant activo.
- MRR, churn mensual y tenants activos por plan.

---

## 10. Alcance Actual y Roadmap

### En alcance activo

- Web POS tenant y backend fiscal.
- Facturacion electronica SUNAT para boleta/factura/notas/resumenes/bajas.
- Inventario con ledger, stock items, compras, lotes y BOM.
- Caja, reportes, notificaciones y asistente AI.
- Control plane con provisioning, billing, cuotas/features, certificados y backups.
- Android POS como cliente offline de la API.

### Fuera de alcance

- Regimenes fiscales no peruanos.
- ERP contable completo con partida doble.
- Fabricacion o certificacion de hardware propio.
- Marketplace e-commerce completo.
- Data sharing entre tenants; el aislamiento sigue siendo permanente salvo decision futura explicita.

### Proximas decisiones recomendadas

- Definir target real de cobertura PHP y ampliar cobertura frontend mas alla de `posHelpers.ts` y `offline/api.ts`.
- ~~Confirmar gateway primario en Peru entre MercadoPago y PayPal.~~ Resuelto: MercadoPago es el gateway primario (alias por defecto + defaults de onboarding/cambio de plan); PayPal sigue disponible como alternativa seleccionable.
- Documentar formalmente estado productivo de Android POS por vertical.
- Definir si lotes/FEFO y BOM son features GA o gated por plan/vertical.
- Retirar aliases legacy `App\Core\*` cuando no queden consumidores y el guardrail refleje los paquetes `nubitio/*`.

Plan de crecimiento recomendado: mantener este PRD como fuente operacional y abrir documentos dedicados solo cuando exista un owner activo.

---

## 11. Criterios De Aceptacion Para Specs

- Criterios de aceptacion del slice verificados.
- Tests backend relevantes pasan con PHPUnit.
- `vendor/bin/grumphp run` sin fallos para cambios backend.
- `yarn tsc --noEmit`, Vitest y ESLint pasan para cambios frontend.
- Playwright happy path sin regresion cuando se toca POS.
- No romper outbox/offline si se toca venta, catalogo o sincronizacion.
- No romper circuit breaker, retry ni consulta CDR si se toca facturacion.
- No introducir dependencias de dominio dentro de paquetes reutilizables `nubitio/*` ni nuevos shims `App\Core`.
- No mezclar logica tenant con control plane salvo puertos/adaptadores documentados.
