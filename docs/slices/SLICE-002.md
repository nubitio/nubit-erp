# SLICE-002 — Corregir TypeError en `CashSessionResolver` cuando `REQUIRE_CASH_REGISTER=false`

| Field        | Value                                      |
|--------------|--------------------------------------------|
| id           | SLICE-002                                  |
| status       | approved                                   |
| created_at   | 2026-03-02                                 |
| updated_at   | 2026-03-02 (approved)                      |
| author       | agt-architect                              |

---

## 1. Purpose & Scope

### In scope
- Corregir el `TypeError` en `CashSessionResolver::validateCandidate()` que ocurre
  cuando la flag `REQUIRE_CASH_REGISTER=false` y no existe ninguna `cash_session`
  abierta para la sucursal.
- Asegurar que la lógica de `CashSessionResolver` y `SaleProcessor` sea consistente
  con el flag `REQUIRE_CASH_REGISTER`: si no se requiere caja, la ausencia de
  `cash_session` debe manejarse de forma segura (no crash).

### Out of scope
- Cambios en la UI del POS para mostrar/ocultar el control de caja.
- Cambios en la lógica de apertura/cierre de turnos (`CashShift`).
- Cambios en la autenticación o la lógica de permisos.

---

## 2. Root-cause analysis

### Bug primario — `$candidate = null` en `CashSessionResolver`

En `CashSessionResolver::resolveForSale()` (línea 39), la variable `$candidate`
se inicializa a `null`:

```php
$candidate = null;                          // ← inicialización incorrecta
if ($legacyCashShiftId !== null) {
    $candidate = $this->findByLegacyCashShift(...);   // devuelve array|false
}
if ($candidate === false && ...) { ... }    // ← nunca se cumple si $candidate es null
// ... demás búsquedas tampoco se ejecutan
if ($candidate === false) {
    throw new ServiceException(...);        // ← tampoco se lanza
}
$this->validateCandidate($candidate, ...); // ← $candidate sigue siendo null → TypeError
```

Cuando `$requireCashShift=false` y no hay `CashShift` abierto, el
`$legacyCashShiftId` llega como `null` al resolver, la primera rama se salta, y
`$candidate` nunca cambia de `null` a `false`. Todas las comprobaciones
`=== false` se bypasean silenciosamente, y `validateCandidate` recibe `null`
cuando espera `array`.

**Fix inmediato:** Cambiar la inicialización a `$candidate = false`.

### Bug secundario — El `SaleProcessor` siempre llama al resolver aunque no haya caja

Incluso con el fix primario, cuando `REQUIRE_CASH_REGISTER=false` y no existe
ninguna `cash_session` en la base de datos para esa sucursal, el resolver lanza
`ServiceException('No se pudo resolver cash_session_id...')`, lo que bloquea
igualmente la venta.

El flujo correcto cuando `REQUIRE_CASH_REGISTER=false` es:
- Si hay `cash_session` disponible → usarla (comportamiento actual correcto).
- Si **no** hay `cash_session` → **no** asignar `cash_session_id` y continuar
  sin ella (la venta se graba con `cash_session_id = null`).

**Fix secundario:**
Propagar la información de "caja requerida" hasta el `CashSessionResolver` (o
manejarlo en `SaleProcessor` antes de llamar al resolver) para que cuando
`REQUIRE_CASH_REGISTER=false` y no se encuentra ninguna sesión, se retorne
`null` en lugar de lanzar excepción.

### Resumen de cambios necesarios

| Archivo | Cambio |
|---------|--------|
| `src/State/Sale/CashSessionResolver.php` | Inicializar `$candidate = false` (línea 39). Añadir método `tryResolveForSale()` que retorna `?int` en lugar de lanzar si no hay sesión. |
| `src/State/Sale/SaleProcessor.php` | Cuando `$requireCashShift=false`, usar `tryResolveForSale()` que puede devolver `null`; continuar sin cash_session si es null. |

---

## 3. Domain context

- **Bounded context primario:** POS / Sales
- **Bounded context secundario:** Cash Management (CashSession / CashShift)
- **Entidades involucradas:** `Sale`, `CashShift`, `CashSession` (tabla)

---

## 4. User story

> Como cliente que no gestiona caja en el POS (usa ERP externo u otro sistema),
> cuando la flag `REQUIRE_CASH_REGISTER` está desactivada para mi tenant,
> quiero poder registrar ventas sin tener una sesión de caja abierta,
> para que el POS opere sin bloqueos relacionados con el control de caja.

---

## 5. Comportamiento esperado (antes → después)

### Escenario A — `REQUIRE_CASH_REGISTER=true` (comportamiento actual, no cambia)
- Sin caja abierta → `ServiceException: No puedes realizar ventas, no tienes una caja abierta` ✅

### Escenario B — `REQUIRE_CASH_REGISTER=false`, hay `cash_session` disponible (no cambia)
- Se resuelve la sesión → venta se graba con `cash_session_id` ✅

### Escenario C — `REQUIRE_CASH_REGISTER=false`, **no** hay `cash_session` (BUG actual)
- **Antes:** `TypeError → ServiceException: No se pudo sincronizar la venta con Sales V2` ❌
- **Después:** Venta se graba con `cash_session_id = null`; operación exitosa ✅

---

## 6. API surface

Sin cambios en contratos de API. El response de `POST /api/sales` no cambia para
el escenario B. Para el escenario C, la venta completa sin error.

---

## 7. Domain events

Ninguno nuevo.

---

## 8. Data requirements

No se requieren migraciones. La columna `cash_session_id` en `sales` ya es
`nullable` (el escenario C simplemente la deja en `null`).

---

## 9. Dependent artifacts

| Artifact | Tipo | Estado |
|----------|------|--------|
| `CashSessionResolver` | Código existente | Buggy |
| `SaleProcessor` | Código existente | Buggy |
| `Constants::REQUIRE_CASH_REGISTER` | Código existente | Correcto |

---

## 10. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | Fix `$candidate = false` en `CashSessionResolver`; añadir `tryResolveForSale(): ?int`; actualizar `SaleProcessor` para usar `tryResolveForSale` cuando `!$requireCashShift` | `agt-backend` |
| T2 | Tests unitarios de `CashSessionResolver` cubriendo: init con `null` legacy shift, no hay sesión + `requireCashShift=false`, no hay sesión + `requireCashShift=true` | `agt-backend` |
| T3 | Test de integración/funcional en `SaleProcessor`: venta exitosa cuando `REQUIRE_CASH_REGISTER=false` y sin `cash_session` | `agt-qa` |

---

## 11. Acceptance criteria

1. `REQUIRE_CASH_REGISTER=true` + sin caja → `ServiceException` con mensaje "No tienes una caja abierta" (comportamiento **sin cambio**).
2. `REQUIRE_CASH_REGISTER=false` + sin `cash_session` → venta procesada exitosamente, sin excepción, con `cash_session_id = null` en la tabla `sales`.
3. `REQUIRE_CASH_REGISTER=false` + con `cash_session` disponible → venta procesada con el `cash_session_id` correcto (comportamiento **sin cambio**).
4. `CashSessionResolver` nunca lanza `TypeError`; el `$candidate` se inicializa como `false`.
5. Tests unitarios de `CashSessionResolver` cubren los tres escenarios.
6. `make check` pasa en verde.

---

## 12. Open questions / risks

| # | Pregunta | Riesgo |
|---|----------|--------|
| Q1 | Cuando `cash_session_id = null`, ¿el `SalesWriteGateway` lo acepta o requiere un ID válido? Verificar `SalesWriteGateway::write()` antes de implementar. | Medio — puede requerir ajuste en el gateway. |
| Q2 | ¿Los movimientos de caja (`writeCashSaleMovement`) deben omitirse cuando `cash_session_id = null`? | Bajo — ya están protegidos por `if ($resolvedCashSessionId !== null)` en `SaleProcessor` línea 308. |
