# POS DB V2 - Operacion Steady-State

**Estado:** Activo  
**Fecha:** 2026-02-28  
**Sistema:** `efact`

## Objetivo

Operar POS con `sales*`, `stock_movements`, `cash_sessions` y read-models como fuente principal, manteniendo controles diarios de integridad por tenant.

## Comandos Operativos

### Reconciliaciones recomendadas

```bash
php bin/console app:purchases:v2:reconcile --tenant=mi_tenant --days=30
```

## Criterios de Salud Minimos

1. Checks SQL de ventas V2 sin gaps fuera de tolerancia (ver roadmap historico).
2. Sin mismatch de `cash_session`/`branch`.
3. Sin violaciones de integridad de pagos.
4. Gap de stock y monetario dentro de tolerancia.

## Runbook de Incidente (resumen)

1. Congelar escritura de entidad afectada por feature flag.
2. Aislar tenant/sucursal/caja afectada.
3. Ejecutar diagnostico y cuantificar brecha.
4. Corregir con script idempotente auditado.
5. Reconciliar summaries/read-models.
6. Reabrir escritura y monitorear 24h.

## Referencias

- Arquitectura objetivo: `docs/pos-database-architecture-10x.md`
- Archivo historico de migracion: `docs/archive/pos-v2-cutover/pos-database-migration-roadmap-10x.md`
