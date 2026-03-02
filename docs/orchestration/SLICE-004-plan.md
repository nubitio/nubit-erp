# Orchestration Plan — SLICE-004
## `NotNormalizableValueException` en `PATCH /api/products` para `lotTrackingStrategy`

| Field         | Value                                              |
|---------------|----------------------------------------------------|
| slice         | SLICE-004                                          |
| branch        | slice/004-lot-tracking-strategy-denormalize        |
| orchestrator  | agt-orchestrate                                    |
| created_at    | 2026-03-02                                         |
| status        | in_progress                                        |

---

## Root Cause Summary

`Product::setLotTrackingStrategy()` tenía el parámetro tipado como
`LotTrackingStrategy` (backed enum). Symfony Serializer pasa el string `"NONE"`
directamente al setter durante la deserialización del PATCH → type mismatch →
`NotNormalizableValueException` → HTTP 500.

**Fix:** Union type `string|LotTrackingStrategy` con coerción interna via
`LotTrackingStrategy::from($strategy)` cuando recibe string.

---

## Task Decomposition

| Task ID  | Title                                                      | Agent         | Wave | Status  |
|----------|------------------------------------------------------------|---------------|------|---------|
| TASK-010 | Fix setter `Product::setLotTrackingStrategy()` + unit test | `agt-backend` | 1    | pending |
| TASK-011 | Gate: `make check` verde                                   | `agt-qa`      | 2    | pending |

---

## Gate Checkpoints

| Gate | Command      | After            | Required result | Actual result |
|------|--------------|------------------|-----------------|---------------|
| G1   | `make check` | Wave 1 complete  | All green       | pending       |

---

## Artifact Register

| Artifact ID             | Type        | File                        | Status    |
|-------------------------|-------------|-----------------------------|-----------|
| ART-S004-001-product    | code change | `src/Entity/Product.php`    | delivered |
| ART-S004-002-unit-test  | test        | `tests/Entity/ProductLotTrackingStrategyTest.php` | pending |
| ART-S004-003-gate-report| report      | —                           | pending   |
