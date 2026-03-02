# SLICE-004 — Corregir `NotNormalizableValueException` en `PATCH /api/products` para `lotTrackingStrategy`

| Field        | Value                                         |
|--------------|-----------------------------------------------|
| id           | SLICE-004                                     |
| status       | approved                                      |
| created_at   | 2026-03-02                                    |
| updated_at   | 2026-03-02                                    |
| author       | agt-architect                                 |

---

## 1. Purpose & Scope

### In scope
- Corregir el `NotNormalizableValueException` que ocurre en `PATCH /api/products/{id}`
  cuando el body incluye `"lotTrackingStrategy": "NONE"` (o cualquier valor string
  válido del enum).
- El setter `Product::setLotTrackingStrategy()` actualmente exige un parámetro de
  tipo `LotTrackingStrategy` (backed enum), pero el serializador de Symfony/API Platform
  le pasa el string crudo `"NONE"` → type mismatch → excepción 400.

### Out of scope
- Cambios en la lógica de negocio de lot tracking.
- Cambios en `StockLot`, `InventoryLedgerService` u otras clases que usan
  `getLotTrackingStrategy()` / `getLotTrackingStrategyEnum()`.
- Cambios en el esquema de base de datos (la columna ya es `VARCHAR`).

---

## 2. Root-cause analysis

### El bug

`Product::$lotTrackingStrategy` se almacena como `string` en la BD (columna
`lot_tracking_strategy VARCHAR(10)`). Por eso la propiedad está tipada como
`string` (línea 94 de `Product.php`). El getter devuelve `string`. ✅

El **setter**, sin embargo, está tipado con el backed enum:

```php
public function setLotTrackingStrategy(LotTrackingStrategy $strategy): static
{
    $this->lotTrackingStrategy = $strategy->value;
    return $this;
}
```

Cuando Symfony Serializer deserializa `{"lotTrackingStrategy": "NONE"}` para un
`PATCH`, descubre mediante reflexión que el setter requiere `LotTrackingStrategy`.
El `BackedEnumNormalizer` solo se activa automáticamente cuando la **propiedad** está
tipada como enum, no cuando solo el **parámetro del setter** lo está. En este
escenario, el serializer intenta pasar el `string "NONE"` directamente al setter →
`InvalidTypeException` → `NotNormalizableValueException`.

### Solución

Cambiar el setter para aceptar `string|LotTrackingStrategy` y coercionar
internamente:

```php
public function setLotTrackingStrategy(string|LotTrackingStrategy $strategy): static
{
    $this->lotTrackingStrategy = $strategy instanceof LotTrackingStrategy
        ? $strategy->value
        : LotTrackingStrategy::from($strategy)->value;   // valida y normaliza
    return $this;
}
```

Esto:
1. Acepta el string `"NONE"` que llega del serializador → pasa `LotTrackingStrategy::from()` → válido.
2. Sigue aceptando instancias del enum si algún código interno lo llama con el enum.
3. Valida implícitamente: si el string no es un case válido del enum, `::from()` lanza
   `ValueError`, que API Platform convierte en 422 Unprocessable Entity. ✅
4. No requiere cambios en ningún llamador externo (no hay ninguno).

### Resumen de cambios

| Archivo | Cambio |
|---------|--------|
| `src/Entity/Product.php` | `setLotTrackingStrategy(string\|LotTrackingStrategy $strategy)` — aceptar union type y coercionar |

---

## 3. Domain context

- **Bounded context:** Inventory / Product Catalog
- **Entidad involucrada:** `Product`

---

## 4. User story

> Como usuario del backoffice que edita un producto, quiero poder guardar la
> estrategia de tracking de lotes (`lotTrackingStrategy`) desde el formulario sin
> que la API devuelva error 400/500, para que los cambios se persistan correctamente.

---

## 5. Comportamiento esperado (antes → después)

| Escenario | Antes | Después |
|-----------|-------|---------|
| `PATCH /api/products/{id}` con `"lotTrackingStrategy": "NONE"` | 500 `NotNormalizableValueException` | 200 OK — producto actualizado |
| `PATCH /api/products/{id}` con `"lotTrackingStrategy": "FIFO"` | 500 `NotNormalizableValueException` | 200 OK — producto actualizado |
| `PATCH /api/products/{id}` con `"lotTrackingStrategy": "INVALID"` | 500 (crash) | 422 Unprocessable Entity (error de validación) |

---

## 6. API surface

Sin cambios en el contrato de API. `PATCH /api/products/{id}` con grupo
`product:write` ya acepta `lotTrackingStrategy` como campo; el fix lo hace
funcionar correctamente.

---

## 7. Data requirements

Sin migraciones. La columna ya es `VARCHAR(10)`.

---

## 8. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | Fix `setLotTrackingStrategy()` en `Product.php` — union type + coerción interna | `agt-backend` |
| T2 | Test unitario: setter acepta string y enum; string inválido lanza `ValueError` | `agt-backend` |
| T3 | Gate: `make check` verde | `agt-qa` |

---

## 9. Acceptance criteria

1. `PATCH /api/products/{id}` con `"lotTrackingStrategy": "NONE"` devuelve 200 OK.
2. `PATCH /api/products/{id}` con `"lotTrackingStrategy": "INVALID"` devuelve 422 (no 500).
3. `Product::setLotTrackingStrategy()` acepta `string|LotTrackingStrategy`.
4. `Product::setLotTrackingStrategy('NONE')` y `setLotTrackingStrategy(LotTrackingStrategy::NONE)` producen el mismo resultado.
5. Tests unitarios del setter cubren string válido, enum directo, y string inválido.
6. `make check` verde.
