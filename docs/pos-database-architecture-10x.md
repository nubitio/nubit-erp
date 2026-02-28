# Arquitectura de Base de Datos POS 10x (Database-per-Tenant)

**Estado:** Arquitectura objetivo cerrada (V2 authoritative en operacion)  
**Fecha:** 2026-02-28  
**Contexto:** POS SaaS multi-sucursal, multi-caja, multi-almacen, offline-first, escalable.

## Implementacion y Operacion

Para operacion vigente (controles diarios y runbook), ver:

- `docs/pos-database-operations-steady-state.md`

## 1. Principios de Arquitectura

1. Aislamiento de tenant por infraestructura: **1 base de datos (o schema) por tenant**.
2. Integridad transaccional fuerte: FKs estrictas, constraints de dominio, idempotencia server-side.
3. Modelo transaccional normalizado + proyecciones de lectura para reportes rapidos.
4. Inventario en ledger (kardex): no se actualiza stock "a mano" sin movimiento.
5. Diseno "enterprise-first": soportar devoluciones, pagos mixtos, auditoria, SUNAT y growth 10x.

## 2. Alcance del Modelo Objetivo

- Jerarquia: `tenants (control plane), branches, cash_registers, warehouses`.
- Ventas: `sales`, `sale_items`, `sale_payments`, `sale_discounts`, `sale_taxes`.
- Devoluciones: `returns`, `return_items`, `refund_payments`.
- Inventario: `stock_movements`, `stock_balances`, `stock_transfers`.
- Caja: `cash_sessions`, `cash_movements`, `cash_counts`.
- Fiscal SUNAT: `tax_documents`, `tax_document_lines`, `tax_document_events`.
- Offline robusto: `sync_inbox`, `sync_outbox_server`, `conflict_resolutions`.
- Auditoria: `audit_log`, `price_history`, `entity_versions`.
- Reporteria: `sales_daily_summary`, `product_daily_summary`, `cashier_daily_summary`, `branch_daily_summary`, `payment_method_daily_summary`.

## 3. Modelo Conceptual (Simplificado)

```text
Branch
 |- CashRegister
 |   |- CashSession
 |      |- CashMovement
 |      |- CashCount
 |
 |- Warehouse
 |   |- StockBalance
 |   |- StockMovement
 |   |- StockTransfer (from/to warehouse)
 |
 |- Sale
 |   |- SaleItem
 |   |- SalePayment
 |   |- SaleDiscount
 |   |- SaleTax
 |   |- Return
 |      |- ReturnItem
 |      |- RefundPayment
 |   |- TaxDocument
 |      |- TaxDocumentLine
 |      |- TaxDocumentEvent
 |
 |- Daily Summaries
```

## 4. Especificacion de Tablas (Nivel Arquitectonico)

## 4.1 Jerarquia operativa

### `branches`
- `id` PK
- `company_id` FK -> compania del tenant
- `code`, `name`, `status`
- `created_at`, `updated_at`, `deleted_at`
- Unico: `(code)`

### `cash_registers`
- `id` PK
- `branch_id` FK
- `code`, `name`, `status`
- `created_at`, `updated_at`, `deleted_at`
- Unico: `(branch_id, code)`

### `warehouses`
- `id` PK
- `branch_id` FK
- `code`, `name`, `type` (`SELLABLE`, `TRANSIT`, `DAMAGED`, etc.)
- `created_at`, `updated_at`, `deleted_at`
- Unico: `(branch_id, code)`

## 4.2 Ventas y pagos

### `sales`
- `id` PK (UUID recomendado)
- `branch_id` FK, `warehouse_id` FK, `cash_register_id` FK, `cash_session_id` FK
- `customer_id` FK nullable
- `seller_user_id` FK
- `status` (`DRAFT`, `COMPLETED`, `VOIDED`, `PARTIALLY_RETURNED`, `RETURNED`)
- `currency`, `exchange_rate`
- Totales: `subtotal`, `discount_total`, `tax_total`, `grand_total`
- `paid_total`, `change_total`, `balance_due`
- `issued_at`, `created_at`, `updated_at`, `deleted_at`
- Offline: `local_uuid`, `idempotency_key`, `sync_status`, `synced_at`, `device_id`
- Unicos: `(idempotency_key)`, `(local_uuid)` opcional por dispositivo

### `sale_items`
- `id` PK
- `sale_id` FK
- `line_no`
- `product_id` FK, `variant_id` FK nullable
- Snapshot comercial: `sku_snapshot`, `name_snapshot`, `uom_snapshot`
- Cantidades y precios: `qty`, `unit_price`, `gross_amount`, `discount_amount`, `tax_amount`, `net_amount`
- Costeo: `unit_cost_snapshot`, `margin_amount`
- Unico: `(sale_id, line_no)`

### `sale_payments`
- `id` PK
- `sale_id` FK
- `payment_method_id` FK
- `amount`, `currency`
- `tendered_amount`, `change_amount`
- Metadata: `authorization_code`, `card_brand`, `last4`, `gateway_ref`, `wallet_ref`
- `paid_at`
- Indice: `(sale_id, paid_at)`, `(payment_method_id, paid_at)`

### `sale_discounts`
- `id` PK
- `sale_id` FK
- `sale_item_id` FK nullable (si es descuento global, va null)
- `discount_type` (`PERCENT`, `AMOUNT`, `PROMO`)
- `source` (`MANUAL`, `RULE_ENGINE`, `COUPON`)
- `value`, `amount_applied`, `reason_code`, `reason_text`

### `sale_taxes`
- `id` PK
- `sale_id` FK
- `sale_item_id` FK nullable
- `tax_code` (IGV, ISC, etc.), `tax_rate`, `taxable_base`, `tax_amount`

## 4.3 Devoluciones operacionales

### `returns`
- `id` PK
- `sale_id` FK
- `branch_id` FK, `warehouse_id` FK, `cash_session_id` FK nullable
- `reason_code`, `reason_text`
- `status` (`DRAFT`, `COMPLETED`, `VOIDED`)
- `subtotal`, `tax_total`, `refund_total`
- `created_by`, `created_at`, `updated_at`

### `return_items`
- `id` PK
- `return_id` FK
- `sale_item_id` FK
- `qty_returned`, `unit_price_snapshot`, `tax_amount`, `line_total`
- Unico recomendado: `(return_id, sale_item_id)`

### `refund_payments`
- `id` PK
- `return_id` FK
- `payment_method_id` FK
- `amount`, `refunded_at`, `gateway_ref`

## 4.4 Inventario (Kardex Ledger)

### `stock_movements` (fuente de verdad)
- `id` PK
- `occurred_at`, `created_at`
- `warehouse_id` FK
- `product_id` FK, `variant_id` FK nullable
- `movement_type` (`SALE_OUT`, `RETURN_IN`, `PURCHASE_IN`, `TRANSFER_OUT`, `TRANSFER_IN`, `ADJUSTMENT_POS`, `ADJUSTMENT_NEG`, `WASTE_OUT`)
- `quantity_delta` (positivo/negativo)
- `unit_cost`, `total_cost`
- `source_type`, `source_id` (sale_item, return_item, transfer_item, adjustment, purchase_item)
- `reference_code`, `note`
- Offline: `local_uuid`, `idempotency_key`, `sync_status`, `synced_at`
- Unico: `(idempotency_key)`
- Indices: `(warehouse_id, product_id, occurred_at)`, `(source_type, source_id)`

### `stock_balances` (derivada para lectura)
- `warehouse_id` FK
- `product_id` FK, `variant_id` FK nullable
- `on_hand_qty`, `reserved_qty`, `available_qty`
- `avg_cost`, `last_movement_at`, `updated_at`
- PK compuesto: `(warehouse_id, product_id, variant_id)`

### `stock_transfers`
- `id` PK
- `from_warehouse_id` FK, `to_warehouse_id` FK
- `status` (`DRAFT`, `IN_TRANSIT`, `RECEIVED`, `CANCELLED`)
- `requested_by`, `approved_by`, `received_by`
- `requested_at`, `received_at`
- `reference_code`, `note`

## 4.5 Caja

### `cash_sessions`
- `id` PK
- `cash_register_id` FK
- `branch_id` FK
- `opened_by` FK, `closed_by` FK nullable
- `status` (`OPEN`, `CLOSED`, `RECONCILING`)
- `opening_amount`, `expected_amount`, `counted_amount`, `difference_amount`
- `opened_at`, `closed_at`
- `close_note`
- Unico recomendado: `(cash_register_id)` filtrado para una sola sesion `OPEN` activa

### `cash_movements`
- `id` PK
- `cash_session_id` FK
- `movement_type` (`SALE_IN`, `REFUND_OUT`, `CASH_IN`, `CASH_OUT`, `OPENING`, `CLOSING_ADJUSTMENT`)
- `category_id` FK nullable
- `sale_id` FK nullable, `return_id` FK nullable
- `amount`, `description`, `created_by`, `created_at`
- Indices: `(cash_session_id, created_at)`, `(sale_id)`, `(movement_type, created_at)`

### `cash_counts`
- `id` PK
- `cash_session_id` FK
- `count_stage` (`OPEN`, `CLOSE`, `RECOUNT`)
- `denomination_type` (`BILL`, `COIN`)
- `denomination_value`
- `quantity`
- `line_total`
- `counted_by`, `counted_at`

## 4.6 Fiscal SUNAT (mantener y mejorar)

### `tax_documents`
- Mantener estructura actual, reforzar:
  - Unico de correlativo: `(document_type, series, number)`
  - `status` y tiempos de envio/aceptacion bien definidos
  - FK a `sale_id`/`return_id` segun caso

### `tax_document_lines`
- Snapshot inmutable por linea fiscal.

### `tax_document_events`
- Bitacora de todos los eventos de envio, consulta, rechazo, aceptacion.
- Recomendado agregar `attempt_no`, `provider`, `latency_ms`.

## 4.7 Offline server-side

### `sync_inbox`
- Eventos recibidos del cliente/dispositivo.
- `id`, `device_id`, `message_type`, `payload_json`, `received_at`, `processed_at`, `status`, `error`.

### `sync_outbox_server`
- Comandos pendientes para dispositivos (si aplica replicacion inversa).
- `id`, `device_id`, `message_type`, `payload_json`, `available_at`, `status`.

### `conflict_resolutions`
- `id`, `entity_type`, `entity_id`, `device_id`, `server_version`, `client_version`, `resolution_strategy`, `resolved_by`, `resolved_at`, `note`.

## 4.8 Auditoria y versionado

### `audit_log`
- `id`, `entity_type`, `entity_id`, `action`, `actor_user_id`, `request_id`, `context_json`, `created_at`.
- Indices: `(entity_type, entity_id, created_at)`, `(actor_user_id, created_at)`.

### `price_history`
- `id`, `product_id`, `price_type` (`SALE`, `PURCHASE`), `old_price`, `new_price`, `changed_by`, `changed_at`, `reason`.

### `entity_versions`
- `id`, `entity_type`, `entity_id`, `version_no`, `snapshot_json`, `created_at`, `created_by`.
- Unico: `(entity_type, entity_id, version_no)`.

## 4.9 Resumenes diarios (reportes rapidos)

### `sales_daily_summary`
- Dimension: `date`, `branch_id`, `warehouse_id`
- Metricas: `sales_count`, `gross_sales`, `discount_total`, `tax_total`, `net_sales`, `avg_ticket`, `items_qty`.

### `product_daily_summary`
- Dimension: `date`, `branch_id`, `warehouse_id`, `product_id`
- Metricas: `qty_sold`, `net_sales`, `cost_total`, `margin_total`, `returns_qty`.

### `cashier_daily_summary`
- Dimension: `date`, `branch_id`, `cashier_user_id`, `cash_session_id`
- Metricas: `sales_count`, `collected_total`, `refund_total`, `cash_difference`.

### `branch_daily_summary`
- Dimension: `date`, `branch_id`
- Metricas consolidadas por sucursal.

### `payment_method_daily_summary`
- Dimension: `date`, `branch_id`, `payment_method_id`
- Metricas: `payment_count`, `amount_total`, `refund_amount_total`.

## 5. Reglas de Integridad Criticas

1. `sales.cash_session_id` obligatorio para ventas POS cerradas.
2. `sales.branch_id` debe coincidir con `cash_sessions.branch_id` y con `warehouses.branch_id`.
3. No permitir `sale_payments` cuya suma no coincida con `sales.paid_total` (salvo credito autorizado).
4. Todo cambio de stock debe crear `stock_movements`.
5. `stock_balances` se actualiza solo por proceso transaccional derivado de `stock_movements`.
6. `tax_document` no se elimina fisicamente; solo cambia estado.
7. `cash_sessions`: una sola sesion `OPEN` por caja.

## 6. Indices Recomendados (sin tenant_id)

Indices base por tabla transaccional:
- `(created_at)`
- `(status, created_at)`
- `(branch_id, created_at)` cuando aplique
- `(warehouse_id, created_at)` cuando aplique

Indices clave por dominio:
- `sales`: `(branch_id, issued_at)`, `(cash_session_id, issued_at)`, `(customer_id, issued_at)`.
- `sale_items`: `(sale_id, line_no)` unico, `(product_id, created_at)`.
- `sale_payments`: `(sale_id)`, `(payment_method_id, paid_at)`.
- `stock_movements`: `(warehouse_id, product_id, occurred_at)`, `(source_type, source_id)`.
- `cash_movements`: `(cash_session_id, created_at)`, `(movement_type, created_at)`.
- `tax_documents`: `(document_type, series, number)` unico, `(status, issue_date)`.

## 7. Estrategia de Reportes

1. OLTP: consultas operativas y detalle.
2. Proyecciones diarias: recalculo incremental (cada 1-5 min) + cierre diario final.
3. Evitar joins pesados para dashboards: leer directo de `*_daily_summary`.
4. Drill-down: desde resumen a tabla transaccional solo para analisis puntual.

## 8. Escalabilidad (Miles de Tenants)

Con database-per-tenant:
1. Automatizar provisioning y migraciones por lote.
2. Catalogo de version de schema por tenant.
3. Backups por tenant con politica tiered (diario, semanal, mensual).
4. Pooling de conexiones + limites por tenant en app.
5. Observabilidad por tenant (latencia, qps, storage, errores SUNAT, backlog sync).
6. Para analytics globales, usar DWH central con ETL/CDC desde cada tenant DB.

## 9. Roadmap de Implementacion (sin downtime)

Resumen historico de fases. Estado vigente: cierre operacional completado. Evidencias y controles:
- Operacion activa: `docs/pos-database-operations-steady-state.md`
- Archivo historico: `docs/archive/pos-v2-cutover/pos-database-migration-roadmap-10x.md`

### Fase 0 - Preparacion
- Crear tablas nuevas en paralelo (sin tocar flujo actual).
- Agregar idempotencia server-side y audit minima.

### Fase 1 - Ventas V2
- Introducir `sale_payments`, `sale_discounts`, `sale_taxes`.
- Dual-write desde flujo actual a nuevo esquema.
- Ejecucion historica detallada: `docs/archive/pos-v2-cutover/pos-database-migration-roadmap-10x.md`.

### Fase 2 - Caja V2
- Introducir `cash_sessions`, `cash_counts` detallado, constraints de sesion activa.
- Amarrar venta -> sesion/caja obligatoriamente.

### Fase 3 - Kardex
- Introducir `stock_movements`, `stock_balances`, `stock_transfers`.
- Migrar actualizaciones directas de stock a pipeline ledger.

### Fase 4 - Devoluciones
- `returns`, `return_items`, `refund_payments` integrados con caja e inventario.

### Fase 5 - Reportes y Corte
- Activar `*_daily_summary`.
- Migrar dashboards.
- Desactivar escritura en tablas legacy de forma controlada.

## 10. Features futuras recomendadas (next wave)

- Compras completas: PO, recepcion, factura proveedor, cuentas por pagar.
- Promociones motor reglas: horarios, combos, coupons, 2x1, cross-sell.
- Combos/Bundles con explosion a componentes para costo y stock real.
- Lotes y vencimientos para rubros sensibles (farmacia/alimentos).
- Lealtad, credito cliente, comisiones cajero, forecast y reposicion automatica.

---

Este documento define el estado objetivo para evolucionar el POS a nivel enterprise sin depender de redisenos posteriores.
