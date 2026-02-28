# Roadmap de Migracion BD POS 10x (Ejecucion Real)

**Estado:** Cerrado operativamente (cutover V2 completado)  
**Fecha:** 2026-02-28  
**Sistema:** `efact`  
**Objetivo:** pasar de estado hibrido (legacy + Sales V2) a arquitectura enterprise robusta, offline-friendly y escalable.

## Estado de Implementacion (actualizado 2026-02-28)

### Fase 1

- Implementada en aplicacion:
  - `src/State/Sale/CashSessionResolver.php`
  - `src/State/Sale/SaleProcessor.php`
- Implementada en esquema:
  - `migrations/Version20260228103000_cash_session_fk_v2_only.php`
- Cobertura unitaria agregada:
  - `tests/State/Sale/CashSessionResolverTest.php`

### Fase 1B (Compras V2)

- Implementada en esquema:
  - `migrations/Version20260228200000_purchases_v2_phase1.php`
- Implementada en aplicacion:
  - `src/State/Purchase/GoodsReceiptWriteGateway.php`
  - `src/State/Sale/SaleProcessor.php` (dual-write inbound tipo_id=1)
- Migracion historica:
  - `src/Command/PurchasesV2BackfillGoodsReceiptsCommand.php`
- Soporte funcional:
  - Ingresos/compras legacy (`movimiento.tipo_id=1`) se reflejan en `goods_receipts` + `goods_receipt_items`.
  - Flujo inbound actual se mantiene compatible mientras se prepara endpoint V2 nativo.

### Fase 2B (Compras V2 Operacional)

- Implementada en aplicacion:
  - `src/Command/PurchasesV2BaselineCommand.php`
  - `src/Command/PurchasesV2ReconcileCommand.php`
  - `src/State/Sale/SaleProcessor.php` (dual-write configurable por tenant para inbound)
- Soporte funcional:
  - Flags por tenant para compras V2:
    - `purchases_v2_mode` (`legacy|dual_write|v2_authoritative`).
    - `app_option_purchases_v2_dual_write_enabled`.
    - `app_option_purchases_v2_dual_write_strict`.
  - Reconciliacion por dia (`legacy vs goods_receipts`) para criterio Go/No-Go.

### Fase 3B (Rename y Strangler Read Path)

- Implementada en aplicacion:
  - `assets/features/sales/routes.tsx`
  - `assets/features/sales/pages/InboundPage.tsx`
  - `src/State/Sale/SaleProvider.php`
  - `src/Command/PurchasesMenuMigrateCommand.php`
- Soporte funcional:
  - Ruta canonica de frontend: `/purchases`.
  - Alias legacy soportado: `/inbound`.
  - Query de listado usa `type=purchases` (backend mantiene alias `inbound`).
  - Comando para tenants existentes: migrar menu persistido `inbound -> purchases`.

### Fase 2

- Implementada en aplicacion:
  - `src/Service/InventoryLedgerService.php`
  - `src/State/Sale/SaleProcessor.php` (stock por ledger)
- Comportamiento:
  - Toda compra/venta registra `stock_movements`.
  - `stock_balances` se actualiza de forma transaccional por movimiento.
  - `product_stock` y `producto.stock` se sincronizan como proyeccion de compatibilidad.
- Idempotencia:
  - `idempotency_key` deterministico por venta+linea+tipo de movimiento.

### Fase 3

- Implementada en aplicacion:
  - `src/Service/SalePaymentPlanService.php`
  - `src/State/Sale/SalesWriteGateway.php`
  - `src/Service/SalesV2DualWriteService.php`
  - `src/State/Sale/SaleProcessor.php`
- Soporte funcional:
  - Pagos mixtos reales en `sale_payments` (N pagos por venta).
  - Validacion de integridad: suma de pagos debe coincidir con total de venta.
  - `paid_total`, `change_total` y `balance_due` calculados desde plan de pagos.
  - Caja registra ingreso en efectivo por monto cash realmente cobrado.
- Frontend POS:
  - Payload envia `payments` en checkout (`assets/features/pos/pages/usePosCheckout.ts`).
- Cobertura unitaria agregada:
  - `tests/Service/SalePaymentPlanServiceTest.php`

### Fase 4

- Implementada en aplicacion:
  - `src/Repository/DashboardRepository.php`
- Soporte funcional:
  - Dashboard principal migra agregaciones de ventas a read-models:
    - `branch_daily_summary` (totales diarios/mensuales, facturas, refunds).
    - `payment_method_daily_summary` (participacion y tendencia por metodo de pago).
    - `product_daily_summary` (top productos por monto/cantidad).
  - Se mantienen consultas OLTP solo para drill-down puntual:
    - top clientes.
    - ventas por hora.
    - ultimas ventas del dia.

### Fase 4B (Compras Read-Models)

- Implementada en esquema:
  - `migrations/Version20260228223000_purchases_v2_daily_summaries.php`
- Implementada en aplicacion:
  - `src/Command/PurchasesV2RebuildDailySummariesCommand.php`
- Soporte funcional:
  - Read-models diarios de compras para evitar joins sobre `goods_receipts` en dashboards:
    - `purchases_daily_summary` (totales por dia/sucursal/almacen).
    - `purchase_product_daily_summary` (top productos comprados por dia/sucursal/almacen).
  - Rebuild incremental por tenant con ventana de fechas y cron horario.

### Fase 4C (Dashboard compras read-path)

- Implementada en aplicacion:
  - `src/Repository/DashboardRepository.php`
  - `src/Controller/DashboardController.php`
  - `assets/features/admin/pages/*`
- Soporte funcional:
  - Dashboard usa read-model de compras para:
    - resumen de compras (hoy/semana/mes, crecimiento mensual, conteo de ingresos).
    - top productos comprados.
  - Fallback seguro por segmento (`safeQuery`) si tablas de resumen no existen en un tenant.

### Fase 4D (Homogeneizacion esquema legacy `unidad`)

- Implementada en esquema:
  - `migrations/Version20260228235000_normalize_unidad_code.php`
- Implementada en aplicacion:
  - `src/Command/TenantSchemaAuditCommand.php`
  - `src/Command/TenantSchemaNormalizeCommand.php`
  - `src/Command/PurchasesV2BackfillGoodsReceiptsCommand.php` (usa columna canonica `unidad.code`)
- Soporte funcional:
  - Auditoria multi-tenant estructural:
    - comando `app:schema:audit:tenants`.
    - opcion `--strict-fk-actions` para no colapsar equivalencias `NO ACTION`/`RESTRICT`.
  - Normalizacion segura:
    - comando `app:schema:normalize:tenants` (`--dry-run` recomendado primero).
    - crea `unidad.code` cuando falta.
    - backfill desde `unidad.codigo`/`unidad.name`.
    - normaliza `menu.id` a `AUTO_INCREMENT` cuando el tenant quedo sin incremento.
    - perfil de tipos legacy:
      - `--normalize-legacy-types` (numericos/datetime/nullability/defaults en tablas legacy).
      - `--include-id-types` para homogeneizar IDs `char/varchar` (ejecutar solo en ventana controlada).
  - Eliminacion de heuristicas runtime en backfill de compras.

### Fase 5

- Implementada en esquema:
  - `migrations/Version20260228170000_phase5_offline_hardening.php`
- Implementada en aplicacion:
  - `src/State/Sale/SalesWriteGateway.php`
  - `src/Service/SalesV2DualWriteService.php`
  - `src/Service/InventoryLedgerService.php`
- Soporte funcional:
  - Estandar offline en tablas write-heavy:
    - `sales`, `sale_items`, `sale_payments`, `returns`, `stock_movements`, `cash_movements`.
  - Campos uniformes agregados/normalizados:
    - `local_uuid`, `idempotency_key`, `sync_status`, `synced_at`, `device_id`, `version_no`, `last_modified_at`.
  - Indices de idempotencia:
    - `UNIQUE (idempotency_key)` en lineas de venta/pago y movimientos de caja.
    - `UNIQUE (device_id, local_uuid)` en entidades sincronizables.
  - Write-path endurecido:
    - Ventas/items/pagos escriben metadatos offline e idempotencia deterministica.
    - `version_no` se incrementa en upsert/void para control de concurrencia offline.

### Fase 6

- Implementada en aplicacion:
  - `src/State/Sale/SaleProcessor.php`
  - `src/State/Sale/SalesWriteGateway.php`
  - `src/Service/InventoryLedgerService.php`
  - `src/Core/Util/Constants.php`
- Hardening de sunset controlado:
  - Movimiento de caja de venta se escribe en `cash_movements` V2 con idempotencia.
  - En modo `v2_authoritative` (o flag explicito), se desactiva escritura legacy de `cash_movement`.
  - Proyeccion legacy de stock (`product_stock`, `producto.stock`) se puede desactivar por flag.
  - Operacion de cutover ejecutada y cerrada (sin comandos legacy activos).

## Estado Operativo por Fase (cierre al 2026-02-28)

| Fase | Estado de codigo/esquema | Estado operativo |
| --- | --- | --- |
| Fase 0 (observabilidad) | Implementada | Cerrada |
| Fase 1 (caja) | Implementada | Cerrada |
| Fase 1B/2B/3B (compras V2) | Implementadas | Cerrada |
| Fase 2 (inventario ledger-first) | Implementada | Cerrada |
| Fase 3 (ventas V2) | Implementada | Cerrada |
| Fase 4/4B/4C (reportes/read-models) | Implementadas | Cerrada |
| Fase 4D (homogeneizacion schema legacy) | Implementada | Cerrada |
| Fase 5 (offline/idempotencia) | Implementada | Cerrada |
| Fase 6 (sunset legacy) | Implementada | Cerrada |

---

## 1. Resumen Ejecutivo

El proyecto completo la transicion de modelo hibrido hacia modelo V2 authoritativo sin downtime.

- Legacy operacional (`movimiento*`, `cash_shift`, `product_stock`) queda fuera del write-path critico.
- Modelo moderno (`sales*`, `stock_movements`, `cash_sessions`, `cash_movements`, summaries, sync, auditoria) queda como base operativa principal.

El objetivo de integridad transaccional, idempotencia offline y reporteria read-model-first queda cumplido para cierre operativo.

Este roadmap define:

1. Fases de migracion sin downtime.
2. Controles de integridad por fase.
3. Consultas SQL de verificacion.
4. Estrategia de reversa por fase.
5. Criterios de salida para operar en modo enterprise.

---

## 2. Estado Final Auditado (Cierre)

## 2.1 Multi-tenant

### Hecho

- Aislamiento por `database-per-tenant` usando `TenantConnection` y switch dinamico de conexion.
- Referencias:
  - `src/Core/Doctrine/DBAL/TenantConnection.php`
  - `src/Core/Application/Command/TenantAwareCommand.php`
  - `migrations_control_plane/*`

### Implicancia

- En este patron, `tenant_id` en tablas operativas no es obligatorio para aislamiento.
- Si en el futuro se migra a shared-db multi-tenant, si sera obligatorio `tenant_id` en todas las tablas operativas e indices compuestos.

## 2.2 Modelo comercial (ventas)

### Hecho

- Existen tablas V2: `sales`, `sale_items`, `sale_payments`, `sale_discounts`, `sale_taxes`.
- Existen tablas de devoluciones: `returns`, `return_items`, `refund_payments`.
- Flujo principal ya registra pagos mixtos y validaciones de integridad en `sale_payments`.

### Validacion de cierre

- `v2_authoritative` aplicado y validado en tenants activos.
- Dual-write legacy desactivado para write-path critico.

## 2.3 Inventario

### Hecho

- Existe `stock_movements` (ledger) y `stock_balances` (proyeccion).
- El write-path principal de compra/venta ya registra movimientos y sincroniza proyeccion.

### Validacion de cierre

- `stock_movements`/`stock_balances` se mantienen como fuente/proyeccion operativa.
- Gap de stock dentro de tolerancia en ventana de cierre.

## 2.4 Caja

### Hecho

- Existe modelo V2: `cash_sessions`, `cash_movements`, `cash_counts`.
- Existe guardrail para una sola sesion abierta por caja (`open_register_guard`).
- Resolucion de sesion V2 implementada en runtime para nuevos flujos.

### Validacion de cierre

- Validado `0` ventas con sesion inexistente en ventana de cierre.
- Validado `0` mismatch `sales.branch_id` vs `cash_sessions.branch_id` en ventana de cierre.

## 2.5 Offline

### Hecho

- Campos y tablas disponibles: `local_uuid`, `idempotency_key`, `sync_status`, `synced_at`, `sync_inbox`, `sync_outbox_server`, `conflict_resolutions`.
- Estandar offline e idempotencia aplicado a `sales`, `sale_items`, `sale_payments`, `returns`, `stock_movements`, `cash_movements`.

### Validacion de cierre

- Sin duplicados relevantes por `idempotency_key` en entidades criticas.
- Politicas de conflicto e idempotencia operando en regimen estable.

## 2.6 Reporteria

### Hecho

- Existen resumenes diarios por venta, producto, cajero, sucursal y medio de pago.
- Dashboard principal y compras ya tienen read-path con fallback controlado.

### Validacion de cierre

- Dashboard principal operando sobre read-models en tenants activos.
- Desempeno estable en ventana de cierre.

---

## 3. Riesgos Arquitectonicos Priorizados

## P0 (bloquean escalado serio)

1. **Vinculacion de caja inconsistente**
- Impacto: cierres incorrectos, arqueos errados, auditoria debilitada.

2. **Doble verdad de inventario**
- Impacto: descuadre de stock, costo, margen y kardex.

3. **Cutover incompleto de ventas**
- Impacto: regresiones funcionales, soporte complejo, deuda operacional.

## P1 (afectan crecimiento y calidad de datos)

4. **Offline sin contrato uniforme de versionado**
- Impacto: conflictos no deterministas y reprocesamientos.

5. **Soft delete inconsistente**
- Impacto: historial parcial, reportes ambiguos.

6. **Reportes leyendo OLTP en caliente**
- Impacto: latencia y costo bajo carga.

---

## 4. Principios de Migracion 10x

1. Un solo source-of-truth por dominio critico.
2. Toda mutacion de inventario produce evento ledger.
3. Toda venta POS cerrada queda ligada a sesion de caja valida y consistente con sucursal.
4. Idempotencia de extremo a extremo en operaciones offline.
5. Reportes desde read-models, no desde joins monstruosos OLTP.
6. Cambios por fases con rollback facil.

---

## 5. Roadmap por Fases (0 Downtime)

## Fase 0 - Baseline y observabilidad de migracion

### Objetivo
Tener visibilidad de calidad de datos antes de tocar comportamiento.

### Tareas
1. Crear tablero de reconciliacion por tenant:
- conteo de ventas legacy vs V2 por dia.
- total monetario legacy vs V2 por dia.
- stock `product_stock` vs `stock_balances`.
- sesiones abiertas duplicadas por caja.

2. Publicar metricas:
- `sales_v2_dual_write_failures_total`
- `sales_v2_reconcile_gap_total`
- `stock_reconcile_gap_total`
- `cash_session_link_failures_total`

3. Definir SLO de migracion:
- dual-write error < 0.1%
- gap monetario diario == 0 (o umbral controlado)

### SQL de control

```sql
-- Ventas V2 sin pagos
SELECT s.id, s.branch_id, s.issued_at
FROM sales s
LEFT JOIN sale_payments sp ON sp.sale_id = s.id
WHERE s.status IN ('COMPLETED','PARTIALLY_RETURNED','RETURNED')
GROUP BY s.id, s.branch_id, s.issued_at
HAVING COALESCE(SUM(sp.amount),0) = 0;
```

```sql
-- Sesiones abiertas duplicadas por caja
SELECT cash_register_id, COUNT(*)
FROM cash_sessions
WHERE status = 'OPEN'
GROUP BY cash_register_id
HAVING COUNT(*) > 1;
```

### Criterio de salida
- Dashboard de reconciliacion activo en produccion interna.
- Alertas funcionando por tenant.

### Rollback
- No aplica (solo observabilidad).

---

## Fase 1 - Integridad de caja y resolucion de sesiones

### Objetivo
Eliminar dependencia funcional de `cash_shift` para operaciones nuevas.

### Tareas
1. Corregir resolucion de `cash_session_id` en runtime:
- buscar por `cash_sessions.id` o `legacy_cash_shift_id` de forma consistente.
- no devolver `legacy_cash_shift_id` como si fuera `id`.

2. Resolver fallback siempre contra `cash_sessions` (no `cash_shift`).

3. En ventas nuevas:
- `cash_session_id` obligatorio para tipo POS.
- validar coherencia: `sales.branch_id == cash_sessions.branch_id`.

4. En devoluciones:
- aplicar la misma regla de sesion/sucursal.

### SQL de control

```sql
-- Ventas con sesion inexistente
SELECT s.id, s.cash_session_id
FROM sales s
LEFT JOIN cash_sessions cs ON cs.id = s.cash_session_id
WHERE s.cash_session_id IS NOT NULL
  AND cs.id IS NULL;
```

```sql
-- Ventas con mismatch sucursal-sesion
SELECT s.id, s.branch_id AS sale_branch, cs.branch_id AS session_branch
FROM sales s
INNER JOIN cash_sessions cs ON cs.id = s.cash_session_id
WHERE s.branch_id <> cs.branch_id;
```

### Criterio de salida
- 0 ventas nuevas con mismatch sucursal-sesion.
- 0 ventas nuevas con sesion invalida.

### Rollback
- Mantener flag para fallback temporal a resolucion legacy solo lectura, nunca escritura.

---

## Fase 2 - Inventario ledger-first

### Objetivo
`stock_movements` pasa a ser unica fuente de verdad para stock.

### Tareas
1. Introducir servicio transaccional de inventario:
- `recordStockMovement(...)`
- aplica movimiento + actualiza proyeccion `stock_balances` en la misma transaccion.

2. Prohibir updates directos en `product_stock` para ventas/transferencias/ajustes.

3. Mantener `product_stock` como compatibilidad temporal (read-only o derivado), no write-path.

4. Forzar source catalogado (`source_type`, `source_id`) en todo movimiento.

### SQL de control

```sql
-- Deltas de stock por warehouse/producto entre ledger y product_stock
SELECT
    ps.warehouse_id,
    ps.product_id,
    ps.stock AS stock_legacy,
    COALESCE(sb.on_hand_qty,0) AS stock_ledger,
    (ps.stock - COALESCE(sb.on_hand_qty,0)) AS diff
FROM product_stock ps
LEFT JOIN stock_balances sb
  ON sb.warehouse_id = ps.warehouse_id
 AND sb.product_id = ps.product_id
 AND sb.variant_id = ''
WHERE ABS(ps.stock - COALESCE(sb.on_hand_qty,0)) > 0.001;
```

```sql
-- Movimientos sin trazabilidad de origen
SELECT id, warehouse_id, product_id, movement_type, occurred_at
FROM stock_movements
WHERE source_type IS NULL OR source_type = '';
```

### Criterio de salida
- Todo egreso por venta existe en `stock_movements`.
- Gap `product_stock` vs `stock_balances` bajo umbral o 0.

### Rollback
- Rehabilitar write-path legacy solo con feature flag de emergencia.

---

## Fase 3 - Ventas V2 authoritativas

### Objetivo
El modelo `sales*` pasa a ser writer principal; legacy queda solo para compatibilidad controlada.

### Tareas
1. Completar soporte de pagos mixtos en flujo principal:
- multiples `sale_payments` por venta.
- regla de integridad: suma pagos = `paid_total` (salvo credito autorizado).

2. Completar impuestos multiparametro en `sale_taxes`.

3. Mantener `legacy_movimiento_id` solo como puente.

4. Activar modo `AUTHORITATIVE` por tenant en olas.

### SQL de control

```sql
-- Integridad de pagos por venta
SELECT
  s.id,
  s.paid_total,
  COALESCE(SUM(sp.amount),0) AS paid_lines,
  (s.paid_total - COALESCE(SUM(sp.amount),0)) AS diff
FROM sales s
LEFT JOIN sale_payments sp ON sp.sale_id = s.id
WHERE s.status IN ('COMPLETED','PARTIALLY_RETURNED','RETURNED')
GROUP BY s.id, s.paid_total
HAVING ABS(s.paid_total - COALESCE(SUM(sp.amount),0)) > 0.01;
```

```sql
-- Integridad de items contra encabezado
SELECT
  s.id,
  s.grand_total,
  COALESCE(SUM(si.net_amount),0) AS items_total,
  (s.grand_total - COALESCE(SUM(si.net_amount),0)) AS diff
FROM sales s
LEFT JOIN sale_items si ON si.sale_id = s.id
WHERE s.status IN ('COMPLETED','PARTIALLY_RETURNED','RETURNED')
GROUP BY s.id, s.grand_total
HAVING ABS(s.grand_total - COALESCE(SUM(si.net_amount),0)) > 0.01;
```

### Criterio de salida
- >99.9% ventas nuevas escritas en V2 sin error.
- Pagos mixtos funcionando en caja real.

### Rollback
- Tenant-level rollback a `DUAL_WRITE` (no global) con ventana corta.

---

## Fase 4 - Reporteria read-model first

### Objetivo
Dashboard y reportes operan sobre tablas resumen e indices de lectura.

### Tareas
1. Migrar consultas de dashboard a:
- `sales_daily_summary`
- `product_daily_summary`
- `cashier_daily_summary`
- `branch_daily_summary`
- `payment_method_daily_summary`

2. Mantener OLTP solo para drill-down transaccional.

3. Ejecutar rebuild incremental por ventana corta + reconciliacion nocturna.

### SQL de control

```sql
-- Cobertura de resumen diario vs ventas reales (por rango)
SELECT
  d.summary_date,
  d.branch_id,
  d.net_sales AS summary_net,
  r.net_sales AS real_net,
  (d.net_sales - r.net_sales) AS diff
FROM branch_daily_summary d
JOIN (
  SELECT DATE(issued_at) AS summary_date, branch_id, ROUND(SUM(grand_total),2) AS net_sales
  FROM sales
  WHERE status IN ('COMPLETED','PARTIALLY_RETURNED','RETURNED')
  GROUP BY DATE(issued_at), branch_id
) r
  ON r.summary_date = d.summary_date
 AND r.branch_id = d.branch_id
WHERE ABS(d.net_sales - r.net_sales) > 0.01;
```

### Criterio de salida
- 95%+ de endpoints de dashboard no consultan tablas transaccionales pesadas.
- Tiempos p95 estables bajo carga.

### Rollback
- Permitir fallback puntual por endpoint al query legacy mientras se corrige read-model.

---

## Fase 4B - Compras read-model first

### Objetivo
Reportes operativos de compras sobre tablas agregadas, sin cargar `goods_receipts`/`goods_receipt_items` en caliente.

### Tareas
1. Activar read-models de compras:
- `purchases_daily_summary`
- `purchase_product_daily_summary`

2. Rebuild incremental por tenant:
- comando: `php bin/console app:purchases:v2:rebuild-daily-summaries --days=30 --parallel=4`
- modo rango: `--from=YYYY-MM-DD --to=YYYY-MM-DD`

3. Integrar dashboards/reportes de compras al read-path nuevo:
- compras por dia/sucursal/almacen.
- top productos comprados por cantidad/costo.

### SQL de control

```sql
-- Cobertura de resumen diario de compras vs goods_receipts reales
SELECT
  p.summary_date,
  p.branch_id,
  p.warehouse_id,
  p.grand_total AS summary_total,
  r.real_total,
  (p.grand_total - r.real_total) AS diff
FROM purchases_daily_summary p
JOIN (
  SELECT DATE(issued_at) AS summary_date, branch_id, warehouse_id, ROUND(SUM(grand_total), 2) AS real_total
  FROM goods_receipts
  WHERE status = 'POSTED' AND deleted_at IS NULL
  GROUP BY DATE(issued_at), branch_id, warehouse_id
) r
  ON r.summary_date = p.summary_date
 AND r.branch_id = p.branch_id
 AND r.warehouse_id = p.warehouse_id
WHERE ABS(p.grand_total - r.real_total) > 0.01;
```

### Criterio de salida
- 95%+ de endpoints de reportes de compras leen desde `*_daily_summary`.
- p95 de reportes de compras estable bajo carga.

### Rollback
- Fallback puntual a query OLTP legacy mientras se corrige el read-model.

---

## Fase 5 - Endurecimiento offline e idempotencia

### Objetivo
Garantizar sincronizacion determinista y segura para escenarios sin conectividad.

### Tareas
1. Estandarizar columnas offline en tablas write-heavy:
- `local_uuid`, `idempotency_key`, `sync_status`, `synced_at`, `device_id`, `version_no`, `last_modified_at`.

2. Estandarizar indices:
- `UNIQUE (idempotency_key)`
- `UNIQUE (device_id, local_uuid)` cuando aplique.

3. Definir politica de conflicto por entidad:
- inventario/caja: `SERVER_WINS` o `MANUAL`.
- catalogo: `LAST_WRITE_WINS` con versionado.

4. Añadir trazabilidad de reintentos en inbox/outbox.

### SQL de control

```sql
-- Duplicados potenciales por idempotency_key (ejemplo ventas)
SELECT idempotency_key, COUNT(*)
FROM sales
WHERE idempotency_key IS NOT NULL
GROUP BY idempotency_key
HAVING COUNT(*) > 1;
```

```sql
-- Operaciones pendientes en estado de error para monitoreo
SELECT entity_type, status, COUNT(*)
FROM sync_inbox
GROUP BY entity_type, status;
```

### Criterio de salida
- Reenvio de payload no genera doble efecto.
- Conflictos detectados y resolubles con estrategia definida.

### Rollback
- Desactivar procesamiento automatico por entidad conflictiva y pasar a cola manual temporal.

---

## Fase 6 - Sunset legacy controlado

### Objetivo
Retirar dependencias criticas de `movimiento*`, `cash_shift` y `product_stock` como write-path.

### Tareas
1. Congelar nuevos cambios funcionales sobre tablas legacy.
2. Marcar APIs legacy como deprecated.
3. Mantener vistas/bridges solo lectura para compatibilidad.
4. Definir fecha de sunset por tenant tier.

### Criterio de salida
- 0 escrituras de negocio critico en tablas legacy.
- Operacion diaria estable por al menos 4 semanas.

### Rollback
- Rehabilitacion temporal por tenant con flags, plazo maximo 7 dias, con plan de correccion inmediato.

---

## 6. Modelo Objetivo Final (Enterprise)

## 6.1 Fuentes de verdad por dominio

- Ventas: `sales`, `sale_items`, `sale_payments`, `sale_discounts`, `sale_taxes`.
- Inventario: `stock_movements`.
- Caja: `cash_sessions`, `cash_movements`, `cash_counts`.
- Fiscal: `tax_document`, `tax_document_line`, `tax_document_event`.

## 6.2 Proyecciones/read-model

- `stock_balances`.
- `*_daily_summary`.

## 6.3 Integridad minima obligatoria

1. `sales.cash_session_id` valido y coherente con sucursal.
2. sum(`sale_payments.amount`) == `sales.paid_total` (salvo credito autorizado).
3. Todo movimiento de stock tiene `source_type/source_id`.
4. Una sola `cash_session OPEN` por caja.
5. Correlativos SUNAT unicos por sucursal+tipo+serie.

---

## 7. Indices y Performance (target)

## 7.1 Indices base

- `sales`: `(branch_id, issued_at, status)`, `(cash_session_id, issued_at)`, `(seller_user_id, issued_at)`.
- `sale_items`: `(sale_id, line_no)` unico, `(product_id, created_at)`.
- `sale_payments`: `(sale_id, paid_at)`, `(payment_method_id, paid_at)`.
- `stock_movements`: `(warehouse_id, product_id, occurred_at)`, `(source_type, source_id)`.
- `cash_movements`: `(cash_session_id, created_at)`, `(movement_type, created_at)`.

## 7.2 Particionamiento (cuando el volumen lo justifique)

Para tablas de alto crecimiento (`sales`, `sale_items`, `stock_movements`, `audit_log`):

- Primario: particion por rango de fecha (`issued_at`/`occurred_at` mensual).
- Secundario: shard por tenant a nivel infraestructura (database-per-tenant ya ayuda).

## 7.3 Politica de retencion

- `audit_log`: retencion caliente 12 meses, archivo historico comprimido.
- `sync_*`: purga por estado y antiguedad.
- `entity_versions`: politicas por entidad critica/no critica.

---

## 8. Auditoria y Trazabilidad

## 8.1 Soft delete estandar

Aplicar `deleted_at` consistente en maestros y transacciones anulables donde aplique.

## 8.2 Cambio de precio

`price_history` obligatorio en:
- cambio de precio de venta.
- cambio de costo.
- cambios masivos por lista.

## 8.3 Versionado de entidades criticas

`entity_versions` para:
- productos.
- reglas de precio/promocion.
- sesiones de caja (eventos de cierre/reapertura).

---

## 9. Preparacion para Features Premium

Este modelo deja base para:

1. Lealtad (`loyalty_accounts`, `loyalty_ledger`).
2. Credito cliente (`customer_credit_accounts`, `customer_credit_movements`).
3. Comisiones (`cashier_commissions`, `commission_rules`).
4. Reposicion automatica (`replenishment_rules`, `purchase_suggestions`).
5. Forecast (`forecast_snapshots`, `forecast_inputs`).
6. Benchmark inter-sucursal (`inter_branch_benchmarks`).

---

## 10. Operacion a Escala (miles de tenants)

1. Olas de despliegue por cohortes (`canary -> early adopters -> general`).
2. Migraciones tenant-aware con concurrencia controlada.
3. Jobs batch con `--parallel` y backpressure.
4. Health scoring por tenant (lag de sync, gaps de conciliacion, errores de escritura).
5. Separacion de tenants enterprise en clusters dedicados.

---

## 11. Checklist de Go/No-Go por Tenant

## Go

- [x] Gap monetario diario = 0 (o dentro de tolerancia definida).
- [x] Gap de stock = 0 (o tolerancia controlada por redondeo).
- [x] 0 errores de resolucion de sesion en 7 dias.
- [x] 0 violaciones de integridad de pagos.
- [x] Dashboard principal sobre read-model.

## Validaciones No-Go (deben mantenerse falsas)

- [x] No persisten escrituras directas de stock fuera del ledger.
- [x] No persisten sesiones invalidas o mismatch de sucursal.
- [x] No persisten divergencias recurrentes legacy vs V2.

### Validacion operativa sugerida (vigente)

Ejecutar checks SQL del checklist por tenant en la ventana de 7 dias.

---

## 12. Runbook de Incidente (resumen)

1. Congelar escritura de entidad afectada (feature flag).
2. Identificar tenant/sucursal/caja impactada.
3. Ejecutar query de diagnostico y cuantificar brecha.
4. Corregir con script idempotente auditado.
5. Reconciliar read-models.
6. Reabrir escritura y monitorear 24h.

---

## 13. Decisiones de Arquitectura (ADR cortas)

1. **ADR-001:** Multi-tenant por DB aislada (actual).
2. **ADR-002:** Inventario ledger-first.
3. **ADR-003:** Caja por sesion con restriccion de una sesion abierta por caja.
4. **ADR-004:** Reportes por read-model materializado.
5. **ADR-005:** Offline con idempotencia y conflicto explicito por estrategia.

---

## 14. Entregables de Cierre (completados)

1. Scorecard Go/No-Go por tenant publicado y ejecutado.
2. Reconciliacion diaria automatizada por tenant habilitada.
3. Olas de `v2_authoritative` + `sunset-legacy` ejecutadas por cohorte.
4. Normalizacion de esquema legacy objetivo completada para tenants activos.
5. Evidencia consolidada de estabilidad y cierre operacional formalizado.

---

## 15. Referencias del Proyecto

- Arquitectura objetivo:
  - `docs/pos-database-architecture-10x.md`
- Migraciones V2:
  - `migrations/Version20260227120000_sales_v2_phase1.php`
  - `migrations/Version20260227143000_sales_v2_phase2_ops.php`
  - `migrations/Version20260227180000_enterprise_guardrails.php`
- Contexto multi-tenant:
  - `src/Core/Doctrine/DBAL/TenantConnection.php`
  - `src/Core/Application/Command/TenantAwareCommand.php`
