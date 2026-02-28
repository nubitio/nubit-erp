# Inventory Core Rollout Checklist (Waves)

Runbook operativo para migrar `Core Inventory` por olas de tenants sin downtime.

## Scope
- Stack: Symfony + Doctrine Migrations + database-per-tenant.
- Objetivo: activar `stock_items` + columnas nuevas en `stock_movements` + políticas por tenant.
- Seguridad: rollback inmediato por tenant con feature flags.

## Pre-Flight (T-1)
1. Verificar salud de contenedor y app:
```bash
docker compose ps
docker compose exec -T web php -v
docker compose exec -T web php bin/console about
```
2. Validar drift estructural antes de tocar tenants:
```bash
docker compose exec -T web php bin/console app:schema:audit:tenants --strict --output=var/schema-audit-pre.json
docker compose exec -T web php bin/console app:schema:normalize:tenants --dry-run
```
3. Normalizar solo si hay drift relevante:
```bash
docker compose exec -T web php bin/console app:schema:normalize:tenants
```
4. Backup lógico por tenant crítico (top revenue):
```bash
docker compose exec -T web php bin/console app:tenant:backup --tenant=<tenant_name>
```

## Fase 1 - Expand Schema (todas las bases)
1. Ejecutar migraciones tenant-aware:
```bash
docker compose exec -T web php bin/console app:tenants:migrate --parallel=4
```
2. Confirmar migración nueva aplicada:
```bash
docker compose exec -T web php bin/console doctrine:migrations:status --env=prod
```
3. Auditoría post-migración:
```bash
docker compose exec -T web php bin/console app:schema:audit:tenants --strict --output=var/schema-audit-post-phase1.json
```

## Fase 2 - Backfill controlado
Ejecutar por tenant en batches fuera de hora pico.

1. Seeds base (idempotentes):
```bash
docker compose exec -T web php bin/console app:seed:initial-data --tenant=<tenant_name>
```
2. SQL de validación mínima (por tenant DB activa):
```sql
SELECT COUNT(*) AS products_missing_base_unit
FROM producto
WHERE base_unit_id IS NULL;

SELECT COUNT(*) AS products_without_unit_mapping
FROM producto p
LEFT JOIN product_units pu ON pu.product_id = p.producto_id
WHERE pu.product_id IS NULL;
```
3. Reconciliación inicial `stock_balances` vs `stock_items`:
```sql
SELECT
  sb.warehouse_id,
  sb.product_id,
  sb.on_hand_qty AS balance_qty,
  COALESCE(si.qty_on_hand, 0) AS item_qty,
  ROUND(sb.on_hand_qty - COALESCE(si.qty_on_hand, 0), 6) AS diff
FROM stock_balances sb
LEFT JOIN stock_items si
  ON si.warehouse_id = sb.warehouse_id
 AND si.product_id = sb.product_id
WHERE ABS(sb.on_hand_qty - COALESCE(si.qty_on_hand, 0)) > 0.001
LIMIT 100;
```

## Fase 3 - Canary (1-5% tenants)
Para cada tenant canary:

1. Activar dual-write inventario (sin cambiar lectura):
```sql
INSERT INTO config (name, type, value)
VALUES ('inventory_core_mode', 'string', 'dual_write')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_dual_write_enabled', 'bool', 'true')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_read_stock_items_enabled', 'bool', 'false')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```
2. Política de stock por tenant:
```sql
INSERT INTO tenant_inventory_policy (tenant_id, policy)
VALUES ('<tenant_id_or_default>', 'STRICT')
ON DUPLICATE KEY UPDATE policy = VALUES(policy);
```
3. Smoke test funcional:
- Venta de producto `SERVICE`: no debe generar movimiento de stock.
- Venta de producto `STOCKABLE`: debe escribir `stock_movements` y materializar `stock_items`.

4. Reconciliación de canary (cada 15-30 min):
```sql
SELECT
  sm.warehouse_id,
  sm.product_id,
  ROUND(SUM(sm.qty_delta_base), 6) AS ledger_sum,
  COALESCE(si.qty_on_hand, 0) AS stock_item_qty,
  ROUND(SUM(sm.qty_delta_base) - COALESCE(si.qty_on_hand, 0), 6) AS diff
FROM stock_movements sm
LEFT JOIN stock_items si
  ON si.warehouse_id = sm.warehouse_id
 AND si.product_id = sm.product_id
GROUP BY sm.warehouse_id, sm.product_id, si.qty_on_hand
HAVING ABS(diff) > 0.001
LIMIT 200;
```

## Fase 4 - Expandir a 25% / 50% / 100%
Repetir exactamente la secuencia de Fase 3 por cohortes.

Criterio para avanzar de ola:
- `diff > 0.001` en reconciliación: 0 filas o solo casos investigados y corregidos.
- Error rate de ventas sin incremento anómalo.
- Sin incidentes P1/P2 por 24h en ola actual.

## Fase 5 - Cutover de lectura (por olas)
Para tenant estable en dual-write:
```sql
INSERT INTO config (name, type, value)
VALUES ('inventory_core_read_stock_items_enabled', 'bool', 'true')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

Post-cutover:
1. Reconciliación cada hora por 24h.
2. Dashboard de tickets POS por “stock insuficiente” y “sin stock”.

## Fase 6 - Autoritativo
Cuando tenant esté estable:
```sql
INSERT INTO config (name, type, value)
VALUES ('inventory_core_mode', 'string', 'v2_authoritative')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_dual_write_enabled', 'bool', 'true')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_read_stock_items_enabled', 'bool', 'true')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

## Rollback (tenant-level, inmediato)
Si una ola falla:
```sql
INSERT INTO config (name, type, value)
VALUES ('inventory_core_read_stock_items_enabled', 'bool', 'false')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_dual_write_enabled', 'bool', 'false')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO config (name, type, value)
VALUES ('inventory_core_mode', 'string', 'legacy')
ON DUPLICATE KEY UPDATE value = VALUES(value);
```

Luego:
1. Revalidar venta/compra en tenant afectado.
2. Abrir incidente y analizar diferencias en `stock_movements`/`stock_items`.
3. Reintentar en ventana controlada.

## Guardrails operativos
- No activar `read_stock_items` en un tenant sin 24h de dual-write limpio.
- Cambios de policy (`STRICT/WARNING_ONLY/ALLOW_NEGATIVE`) solo con ticket aprobado.
- Registrar cada cambio de flag por tenant (quién/cuándo/por qué).
- Máximo 1 ola activa por región/cluster.

## Checklist rápido por tenant
- [ ] Migrado (`app:tenants:migrate`)
- [ ] Backfill validado (`product_units`, `stock_items`)
- [ ] Dual-write ON
- [ ] Reconciliación sin drift > 0.001
- [ ] Read cutover ON
- [ ] Monitoreo 24h OK
- [ ] Autoritativo ON
- [ ] Rollback plan probado
