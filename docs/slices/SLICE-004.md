# SLICE-004 — Corregir `Unknown column 'm.turno_id'` en `SalesV2BackfillCommand`

| Field        | Value                                      |
|--------------|--------------------------------------------|
| id           | SLICE-004                                  |
| status       | done                                       |
| created_at   | 2026-03-02                                 |
| updated_at   | 2026-03-02 (done)                          |
| author       | agt-architect                              |

---

## 1. Purpose & Scope

### In scope
- Corregir la columna inexistente `m.turno_id` referenciada en la subquery de
  `SalesV2BackfillCommand.php` (línea 107), reemplazándola por la resolución
  correcta de la `cash_session` usando `m.caja_id` (columna real de `movimiento`).
- Garantizar que el comando `app:sales:v2:backfill` se ejecute sin errores de
  `SQLSTATE[42S22]: Column not found` en todos los tenants.

### Out of scope
- Cambios en el esquema de `movimiento` (no se añade `turno_id` — la columna no
  debe existir).
- Cambios en la lógica de backfill de `sale_items`.
- Nullability de `cash_session_id` en la tabla `sales` (se mantiene el
  comportamiento actual: `NULL` cuando no hay sesión coincidente).
- Refactoring del resto del comando más allá del mínimo necesario.

---

## 2. Root-cause analysis

### Descripción del bug

El comando `app:sales:v2:backfill` (introducido en commit `09fcda1a`, 2026-03-02)
ejecuta la siguiente subquery SQL para resolver el `cash_session_id` de cada
movimiento legacy:

```sql
(
    SELECT cs.id
    FROM cash_sessions cs
    WHERE cs.legacy_cash_shift_id = m.turno_id   -- ← columna inexistente
      AND cs.branch_id = m.branch_id
    LIMIT 1
)  AS cash_session_id
```

La tabla `movimiento` (alias `m`) **nunca tuvo una columna `turno_id`** en ninguna
migración de la historia del proyecto. El campo de "caja" en `movimiento` es
`caja_id` (referencia a `cash_register`), no `turno_id`.

Al ejecutar el comando sobre cualquier tenant, MySQL lanza:

```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'm.turno_id' in 'WHERE'
```

### Causa raíz

El autor del nuevo comando reemplazó la resolución dinámica PHP que usaba el
comando eliminado `SalesV2BackfillSalesCommand.php` (método `resolveCashContext()`)
por SQL puro, pero asumió incorrectamente que existía una columna `turno_id` en
`movimiento`. En realidad, la resolución debe hacerse a través de:

```
m.caja_id → cash_sessions.cash_register_id
```

con un filtro de ventana temporal opcional:

```
cash_sessions.opened_at  ≤  m.fecha
cash_sessions.closed_at  ≥  m.fecha  (o IS NULL si la sesión sigue abierta)
```

### Solución propuesta

Reemplazar la subquery de `cash_session_id` (líneas 104-110 de
`SalesV2BackfillCommand.php`) por:

```sql
(
    SELECT cs.id
    FROM cash_sessions cs
    WHERE cs.cash_register_id = m.caja_id
      AND cs.branch_id        = m.branch_id
      AND cs.opened_at        <= COALESCE(m.fecha, m.date_created, NOW())
      AND (
          cs.closed_at IS NULL
          OR cs.closed_at >= COALESCE(m.fecha, m.date_created, NOW())
      )
    ORDER BY cs.opened_at DESC
    LIMIT 1
)  AS cash_session_id,
```

Este patrón es consistente con la migración
`Version20260228103000_cash_session_fk_v2_only.php` ya presente en el proyecto.

| Archivo | Cambio |
|---------|--------|
| `src/Command/SalesV2BackfillCommand.php` | Reemplazar subquery `m.turno_id` → join via `m.caja_id` + ventana temporal |

---

## 3. Domain context

- **Bounded context primario:** Sales V2 / Backfill
- **Bounded context secundario:** Cash Management (CashSession / CashShift)
- **Entidades involucradas:** `movimiento` (legacy), `cash_sessions`, `sales`

---

## 4. User story

> Como administrador del sistema que ejecuta la migración de datos legacy,
> quiero que el comando `app:sales:v2:backfill` procese todos los tenants sin
> errores de base de datos, para que los movimientos históricos queden migrados
> correctamente a la tabla `sales`.

---

## 5. Comportamiento esperado (antes → después)

### Escenario A — Ejecución del backfill en cualquier tenant
- **Antes:** El comando falla con `SQLSTATE[42S22]: Column not found: 1054 Unknown
  column 'm.turno_id'` en el INSERT de `sales`. Cero filas migradas. ❌
- **Después:** El INSERT usa `m.caja_id` con ventana temporal para resolver
  `cash_session_id`. Los movimientos con `tipo_id = 2` se migran correctamente.
  Los que no tienen sesión de caja coincidente reciben `cash_session_id = NULL`. ✅

### Escenario B — Movimiento sin `caja_id`
- La subquery retorna `NULL`. El INSERT inserta `cash_session_id = NULL`
  (comportamiento ya contemplado por el comentario en el código). ✅

### Escenario C — Tenant sin tabla `cash_sessions`
- La excepción `TableNotFoundException` ya está capturada en el comando. Sin
  cambio de comportamiento. ✅

---

## 6. API surface

Sin cambios en contratos de API. El comando es CLI únicamente.

---

## 7. Domain events

Ninguno nuevo.

---

## 8. Data requirements

No se requieren migraciones. La corrección es exclusivamente en el SQL del
comando PHP.

---

## 9. Dependent artifacts

| Artifact | Tipo | Estado |
|----------|------|--------|
| `src/Command/SalesV2BackfillCommand.php` | Código existente (buggy) | Necesita corrección en línea 107 |
| `cash_sessions` | Tabla existente | Sin cambio — se usa `cash_register_id` + `opened_at`/`closed_at` |
| `movimiento` | Tabla existente | Sin cambio — se usa `caja_id` (ya mapeada) |

---

## 10. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | En `SalesV2BackfillCommand.php`, reemplazar la subquery de `cash_session_id` (líneas 104-110): sustituir `cs.legacy_cash_shift_id = m.turno_id` por el join via `cs.cash_register_id = m.caja_id` con filtro de ventana temporal (`opened_at`/`closed_at`). | `agt-backend` |
| T2 | Añadir un test funcional / smoke-test del comando con un fixture que incluya una fila en `movimiento` con `caja_id` válido y una `cash_session` coincidente; verificar que `sales.cash_session_id` se resuelve correctamente y no lanza excepción. | `agt-backend` |
| T3 | Gate: `make check` verde; ejecutar el comando en el tenant `altofilete` (staging) y confirmar que no aparece el error `42S22`. | `agt-qa` |

---

## 11. Acceptance criteria

1. El comando `app:sales:v2:backfill` **no lanza** `SQLSTATE[42S22]` en ningún
   tenant con la tabla `movimiento` estándar.
2. La subquery de `cash_session_id` usa **`cs.cash_register_id = m.caja_id`**
   (no `m.turno_id`) como condición de join principal.
3. La subquery incluye filtro de ventana temporal:
   `cs.opened_at <= COALESCE(m.fecha, m.date_created, NOW())` y
   `(cs.closed_at IS NULL OR cs.closed_at >= COALESCE(m.fecha, m.date_created, NOW()))`.
4. Para movimientos con `caja_id = NULL` o sin sesión coincidente, el INSERT
   inserta `cash_session_id = NULL` sin error.
5. `make check` pasa en verde (phpstan, phpcs, phpunit).
6. No hay regresiones en los tests existentes del comando.

---

## 12. Open questions / risks

| # | Pregunta | Riesgo |
|---|----------|--------|
| Q1 | ¿Existen tenants donde `movimiento` tiene una columna extra `turno_id` añadida manualmente fuera de las migraciones? Ejecutar `SHOW COLUMNS FROM movimiento` en `altofilete` para descartar. | Bajo — migraciones gestionan el esquema; poco probable. |
| Q2 | ¿La columna `cash_sessions.cash_register_id` existe en todos los tenants? La migración `Version20260228103000` la introduce — verificar que todos los tenants la tienen aplicada. | Medio — si algún tenant no tiene la migración, el fix podría fallar por `cash_register_id`. Mitigar con `SHOW COLUMNS` previo o usando `legacy_cash_shift_id` vía `cash_shift` como path alternativo. |
| Q3 | `sales.cash_session_id` tiene restricción NOT NULL en algún tenant antiguo? Verificar DDL de `sales` si el INSERT falla con `NULL`. | Bajo — el código ya tiene el comentario `NULL when no matching session exists`. |
