# Core Inventory (multi-tenant, zero-downtime) - eFact

## 1) Estado actual inferido + Assumptions

### Estado inferido (código actual)
- Inventario ya está en transición a modelo ledger-first:
  - `stock_movements` como historial.
  - `stock_balances` como materialización de stock actual.
  - `product_stock` aún existe para compatibilidad legacy.
- Flujo de venta usa `SaleProcessor` + `InventoryLedgerService` y ya descuenta stock por almacén.
- Política de stock negativo existe en `config` como `inventory_negative_stock_policy` con valores legacy: `BLOCK`, `ALLOW_WITH_ROLE`, `ALLOW_ALL`.
- Producto usa esquema legacy (`producto.tipo_id`, `producto.unidad_id`) y tipos numéricos.
- Frontend POS ya consulta stock por almacén y bloquea por `app_option_check_stock`.

### Assumptions
- Stack: Symfony 7 + Doctrine ORM/DBAL + Doctrine Migrations + MySQL/MariaDB.
- Tenant isolation actual: database-per-tenant (no shared-DB); por eso `tenant_id` en tablas operativas puede ser opcional en fase inicial.
- No se elimina ni altera semántica de tablas legacy durante el rollout; solo se agrega y dual-write.
- `stock_items` convivirá con `stock_balances` durante transición para cutover gradual de lecturas.

---

## A) Esquema DB objetivo (Doctrine-ready)

Nota de compatibilidad: en eFact actual las tablas son `producto`, `unidad`, `almacen`. En la arquitectura objetivo equivalen a `products`, `units`, `warehouses`.

### 1. `producto` (extendida)
```sql
ALTER TABLE producto
  ADD COLUMN product_type VARCHAR(20) NOT NULL DEFAULT 'STOCKABLE',
  ADD COLUMN track_inventory TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN base_unit_id INT NULL,
  ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1,
  ADD INDEX idx_producto_product_type (product_type),
  ADD INDEX idx_producto_track_inventory (track_inventory),
  ADD INDEX idx_producto_base_unit (base_unit_id),
  ADD CONSTRAINT fk_producto_base_unit FOREIGN KEY (base_unit_id) REFERENCES unidad(unidad_id);
```

`product_type` permitido:
- `STOCKABLE`
- `SERVICE`
- `DIGITAL`
- `COMPOSITE`
- `CONSUMABLE`

### 2. `unidad` (units)
Ya existe. Se mantiene como catálogo base de unidades (`UND`, `ML`, `GR`, `KG`, etc).

### 3. `product_units`
```sql
CREATE TABLE product_units (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id VARCHAR(255) NOT NULL,
  unit_id INT NOT NULL,
  conversion_to_base DECIMAL(18,6) NOT NULL,
  is_default_sale_unit TINYINT(1) NOT NULL DEFAULT 0,
  purchase_unit_id INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_units_product_unit (product_id, unit_id),
  KEY idx_product_units_product (product_id),
  KEY idx_product_units_default_sale (product_id, is_default_sale_unit),
  CONSTRAINT fk_product_units_product FOREIGN KEY (product_id) REFERENCES producto(producto_id) ON DELETE CASCADE,
  CONSTRAINT fk_product_units_unit FOREIGN KEY (unit_id) REFERENCES unidad(unidad_id),
  CONSTRAINT fk_product_units_purchase_unit FOREIGN KEY (purchase_unit_id) REFERENCES unidad(unidad_id)
);
```

### 4. `almacen` (warehouses)
Ya existe. Se mantiene y se usa como dimensión principal para stock materializado.

### 5. `stock_items` (materialized current stock)
```sql
CREATE TABLE stock_items (
  warehouse_id VARCHAR(255) NOT NULL,
  product_id VARCHAR(255) NOT NULL,
  qty_on_hand DECIMAL(18,6) NOT NULL DEFAULT 0,
  qty_reserved DECIMAL(18,6) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (warehouse_id, product_id),
  KEY idx_stock_items_product_updated (product_id, updated_at),
  CONSTRAINT fk_stock_items_warehouse FOREIGN KEY (warehouse_id) REFERENCES almacen(almacen_id),
  CONSTRAINT fk_stock_items_product FOREIGN KEY (product_id) REFERENCES producto(producto_id)
);
```

### 6. `stock_movements` (ledger, extendida)
Se extiende tabla existente para trazabilidad de unidades:
```sql
ALTER TABLE stock_movements
  ADD COLUMN qty_delta_base DECIMAL(18,6) NOT NULL,
  ADD COLUMN unit_id INT NULL,
  ADD COLUMN qty_original DECIMAL(18,6) NOT NULL,
  ADD INDEX idx_stock_movements_product_happened (product_id, occurred_at),
  ADD INDEX idx_stock_movements_source_compact (source_type, source_id),
  ADD CONSTRAINT fk_stock_movements_unit FOREIGN KEY (unit_id) REFERENCES unidad(unidad_id);
```

`movement_type` objetivo canónico:
- `PURCHASE`, `SALE`, `ADJUSTMENT`, `TRANSFER_IN`, `TRANSFER_OUT`, `RECIPE_CONSUMPTION`

### 7. `tenant_inventory_policy`
```sql
CREATE TABLE tenant_inventory_policy (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tenant_id VARCHAR(128) NOT NULL,
  policy VARCHAR(20) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_inventory_policy_tenant (tenant_id)
);
```

`policy` permitido:
- `STRICT`
- `WARNING_ONLY`
- `ALLOW_NEGATIVE`

### Invariantes
- `stock_movements` es source-of-truth; `stock_items` es read-model derivado.
- Para cada `(warehouse_id, product_id)`:  
  `stock_items.qty_on_hand = SUM(stock_movements.qty_delta_base)`
- `product_type in (SERVICE, DIGITAL)` debe implicar `track_inventory=0`.
- `product_units.conversion_to_base > 0`.
- Solo 1 `is_default_sale_unit=1` por producto.

---

## B) Contratos de extensión (core -> packs)

Implementados en `src/Inventory/Core`:
- Domain events:
  - `StockMovementRecorded`
  - `SaleCompleted`
  - `PurchaseReceived`
- Interfaces/hooks:
  - `StockMovementEnricherInterface`
  - `StockValidationPolicyInterface`
  - `UnitConversionServiceInterface`

Diseño de desacople:
- Core genera movimientos base de venta/compra/ajuste.
- Cada vertical pack puede inyectar un `StockMovementEnricherInterface` para añadir movimientos extra (ejemplo: recetas restaurante).
- Validación de venta negativa es estrategia (`StockValidationPolicyInterface`) por tenant.

---

## C) Plan de migración por fases (0..N)

### Fase 0 - Preparación y flags
- DB:
  - Ninguna ruptura.
  - Confirmar salud de `stock_movements` y `stock_balances`.
- Backend:
  - Introducir flags:
    - `inventory_core_mode`: `legacy|dual_write|v2_authoritative`
    - `inventory_core_dual_write_enabled`
    - `inventory_core_read_stock_items_enabled`
- Validación:
  - Baseline por tenant: conteo de productos con stock, variación diaria, top SKUs.
- Rollback:
  - No aplica (solo preparación).

### Fase 1 - Expand schema online
- DB:
  - Ejecutar migration `Version20260228235900_inventory_core_phase1`.
  - Solo `ADD COLUMN`, `CREATE TABLE`, índices y FK nuevos.
- Backend:
  - Mantener lecturas actuales (`stock_balances`/legacy).
- Rollback:
  - `down()` elimina estructuras nuevas sin tocar tablas legacy.

### Fase 2 - Backfill inicial
- Backfill por lotes (batch 500-2000 filas):
  - `producto.base_unit_id <- producto.unidad_id`
  - `product_units` con factor `1.0` para unidad base.
  - `stock_movements.qty_delta_base <- quantity_delta`.
  - `stock_items <- stock_balances` inicial.
- Verificación:
  - Muestreo por almacén/producto: `stock_balances.on_hand_qty` vs `stock_items.qty_on_hand`.
- Rollback:
  - Desactivar jobs de backfill y volver a sólo legacy read path.

### Fase 3 - Dual-write controlado
- Backend:
  - En ventas/compras/ajustes:
    - seguir escribiendo flujo existente,
    - y adicionalmente actualizar `stock_items` + columnas nuevas (`qty_delta_base`, `unit_id`, `qty_original`).
- Feature flags:
  - Activación por tenant/canary (1%, 5%, 25%, 100%).
- Validación:
  - Reconciliación diaria por tenant:
    - `SUM(ledger)` vs `stock_items`
    - divergencia máxima permitida: `0.001` por producto/almacén.
- Rollback:
  - `inventory_core_dual_write_enabled=false`.

### Fase 4 - Cutover de lectura
- Backend:
  - `getOnHand()` lee `stock_items`; fallback a `stock_balances` si no hay registro.
- Frontend:
  - stock de POS/Kardex lee endpoint ya soportado por `stock_items`.
- Validación:
  - panel de reconciliación en paralelo 7-14 días.
- Rollback:
  - volver flag `inventory_core_read_stock_items_enabled=false`.

### Fase 5 - Política y unidades avanzadas
- Backend:
  - resolver `STRICT/WARNING_ONLY/ALLOW_NEGATIVE` desde `tenant_inventory_policy`.
  - conversiones por `product_units` (ej: copa 150ml -> base ml).
- Frontend:
  - selector de unidad de venta por producto.
  - warning y bloqueo según policy.
- Rollback:
  - policy fallback a `STRICT`, venta en unidad base.

### Fase 6 - Autoritativo + sunset legacy
- Backend:
  - `inventory_core_mode=v2_authoritative` por tenant.
  - congelar escrituras en `product_stock` (solo lectura temporal).
- DB:
  - remover dependencias legacy en release posterior (no en el mismo sprint).
- Rollback:
  - temporal por tenant a `dual_write` con ventana acotada.

---

## D) Cambios backend (Symfony/Doctrine)

### Entidades / repos nuevas
- `tenant_inventory_policy` (repo simple por tenant).
- `product_units` (repo por producto + unidad).
- `stock_items` (read model materializado).

### Servicios
- `InventoryService`
  - `recordMovement(MovementDraft $movement)`
  - `getOnHand(string $warehouseId, string $productId): string`
  - `reserve(...)`, `release(...)`
- `UnitConversionService`
  - Implementado en `src/Inventory/Core/Service/UnitConversionService.php`
  - `toBase()` y `fromBase()`.
- `StockRebuilderJob`
  - Implementado en `src/Inventory/Core/Service/StockRebuilderJob.php`
  - reconstruye `stock_items` desde ledger por lotes.

### Pseudocódigo de integración venta
```php
foreach ($sale->items as $line) {
    if (!$product->trackInventory()) continue;

    $qtyBase = $unitConversion->toBase($productId, $line->unitId, $line->qty);

    $validation = $stockValidation->validateSale(
        product: $product,
        warehouseId: $sale->warehouseId,
        requestedQtyBase: $qtyBase,
        onHandQtyBase: $inventory->getOnHand($sale->warehouseId, $productId),
    );

    if (!$validation->allowed) throw ServiceException(...);
    if ($validation->warning) auditLogger->warning(...);

    $baseMovement = new MovementDraft(... movementType: SALE, qtyDeltaBase: -$qtyBase ...);
    $movements = $enricherChain->enrichSaleMovements($sale, [$baseMovement]);

    foreach ($movements as $movement) {
        $inventory->recordMovement($movement); // escribe ledger + actualiza stock_items
        $eventBus->dispatch(new StockMovementRecorded(...));
    }
}
$eventBus->dispatch(new SaleCompleted(...));
```

### Pseudocódigo compra / ajuste
```php
// Compra
recordMovement(type: PURCHASE, qtyDeltaBase: +qtyBase, sourceType: 'PURCHASE', sourceId: $purchaseId);
dispatch(new PurchaseReceived(...));

// Ajuste
recordMovement(type: ADJUSTMENT, qtyDeltaBase: +/-qtyBase, sourceType: 'ADJUSTMENT', sourceId: $adjustmentId);
```

### Integración con flujo actual
- `SaleProcessor` mantiene comportamiento actual.
- Durante dual-write:
  - sigue `InventoryLedgerService::applyMovementFromSaleItem(...)`
  - añade enriquecimiento y metadata de unidad en nuevo path.
- Política negativa legacy (`BLOCK/ALLOW_*`) se mappea a nueva:
  - `BLOCK -> STRICT`
  - `ALLOW_WITH_ROLE -> WARNING_ONLY` (o regla custom por rol)
  - `ALLOW_ALL -> ALLOW_NEGATIVE`

---

## E) Cambios frontend mínimos

### Producto
- Agregar campos en formulario:
  - `product_type` (`stockable/service/digital/composite/consumable`)
  - `base_unit`
  - unidades de venta (`product_units`, `conversion_to_base`, default sale unit).

### POS
- En línea de venta:
  - selector de unidad (ej: botella/copa).
  - convertir a base antes de enviar (`qty`, `unit_id`).
- Policy UX:
  - `STRICT`: bloquear agregar/cerrar venta si stock insuficiente.
  - `WARNING_ONLY`: permitir, mostrar warning persistente y auditoría.
  - `ALLOW_NEGATIVE`: permitir sin bloqueo, badge “stock negativo”.

### APIs sugeridas
- `GET /products/{id}/units`
- `GET /inventory/policy`
- `POST /movements` con `unit_id`, `qty_original`, `qty_delta_base`.

---

## F) Casos de prueba obligatorios (integración)

1. Servicio no altera stock
- Dado producto `SERVICE`, al cerrar venta no se inserta `stock_movements` ni cambia `stock_items`.

2. Vino por copa (150ml)
- Producto base `ML`, stock inicial `750`.
- Venta `1` copa (`unit_id=copa`, `conversion_to_base=150`).
- Esperado: ledger `qty_delta_base=-150`, `qty_original=1`, stock final `600`.

3. STRICT bloquea sin stock
- `policy=STRICT`, stock `0`, intento de venta `>0`.
- Esperado: error de negocio y no hay movimiento.

4. WARNING_ONLY permite + warning
- `policy=WARNING_ONLY`, stock `0`, venta `1`.
- Esperado: movimiento generado, stock negativo, evento/registro warning.

5. ALLOW_NEGATIVE permite negativo
- `policy=ALLOW_NEGATIVE`, stock `0`, venta `2`.
- Esperado: `stock_items.qty_on_hand=-2`.

6. Reconciliación
- Para ventana `[t1,t2]`, por `(warehouse_id, product_id)`:
  - `SUM(stock_movements.qty_delta_base) == stock_items.qty_on_hand`.
- Test falla si `abs(diff) > 0.001`.

---

## Archivos implementados en esta iteración
- `migrations/Version20260228235900_inventory_core_phase1.php`
- `src/Inventory/Core/Enum/*`
- `src/Inventory/Core/Contract/*`
- `src/Inventory/Core/Event/*`
- `src/Inventory/Core/Dto/*`
- `src/Inventory/Core/Service/UnitConversionService.php`
- `src/Inventory/Core/Service/TenantStockValidationPolicy.php`
- `src/Inventory/Core/Service/StockRebuilderJob.php`
- Ajustes de settings/constantes en `Constants`, `SaleProcessor`, `InitialDataSeeder`, `InitialSetupService`.
