# Orchestration Plan — SLICE-003
## `SendInvoiceMessage` falla con "Invoice not found" (race condition Redis/MySQL)

| Field         | Value                                          |
|---------------|------------------------------------------------|
| slice         | SLICE-003                                      |
| branch        | slice/003-send-invoice-race-condition          |
| orchestrator  | agt-orchestrate                                |
| created_at    | 2026-03-02                                     |
| status        | in_progress                                    |

---

## Root Cause Summary

`SaleProcessor` despacha `SendInvoiceMessage` al transporte Redis (`async`)
inmediatamente después de que `InvoiceCreationService::createForSale()` hace
`$connection->commit()` (DBAL). El worker de Symfony Messenger es un proceso
separado con una conexión MySQL diferente. Bajo `REPEATABLE READ` (default MySQL),
el worker puede consumir el mensaje antes de que la fila `tax_document` sea
visible en su snapshot de conexión → `BillCreateUseCase::find($taxDocumentId)`
retorna `null` → `ServiceException('Invoice not found')` → 3 reintentos
instantáneos → mensaje en cola `failed`.

**Solución:** Usar `DispatchAfterCurrentBusMiddleware` de Symfony Messenger para
diferir el encolado real en Redis hasta que el pipeline del bus HTTP actual haya
completado (post-flush), más `retry_strategy.delay: 2000ms` como red de seguridad.

---

## Task Decomposition

| Task ID  | Title                                                            | Agent         | Wave | Status  |
|----------|------------------------------------------------------------------|---------------|------|---------|
| TASK-007 | Config: `DispatchAfterCurrentBusMiddleware` + retry delay + stamp en SaleProcessor | `agt-backend` | 1    | pending |
| TASK-008 | Tests: dispatch no ocurre si `createForSale` falla; stamp presente en envelope | `agt-backend` | 1    | pending |
| TASK-009 | Gate: `make check` verde; no regresiones en tests de SaleProcessor | `agt-qa`      | 2    | pending |

---

## Waves

### Wave 1 — Implementation + Unit Tests (agt-backend)

Deliver TASK-007 and TASK-008 together. Gate: `make check` must pass green.

---

#### TASK-007 Prompt

```
ORCHESTRATOR → agt-backend
TASK: TASK-007
SLICE: SLICE-003
INPUTS: [ART-S003-001-messenger-config, ART-S003-002-services-config, ART-S003-003-sale-processor]
EXPECTED OUTPUT: [ART-S003-004-messenger-config-updated, ART-S003-005-services-config-updated, ART-S003-006-sale-processor-updated]
DEADLINE GATE: make check must pass
---

## Task: Fix SendInvoiceMessage race condition with DispatchAfterCurrentBusMiddleware

### Context
Project: efact — Symfony 6/7 + API Platform, POS + e-invoicing.
Transport: Redis (`redis://redis:6379/messages`).
Bus: `messenger.bus.default`.

### Problem
`SaleProcessor::process()` (línea 371) despacha `SendInvoiceMessage` al bus
inmediatamente después de que `InvoiceCreationService::createForSale()` hace
`$connection->commit()`. El worker Redis consume el mensaje antes de que la fila
`tax_document` sea visible → `BillCreateUseCase::find()` → null → "Invoice not
found" → 3 reintentos → cola `failed`.

### Files to modify

#### 1. `config/services.yaml`
Añadir el middleware `DispatchAfterCurrentBusMiddleware` al bus default,
ANTES de los middleware de tenant ya existentes:

```yaml
    Symfony\Component\Messenger\Middleware\DispatchAfterCurrentBusMiddleware:
        tags:
            - { name: messenger.middleware, bus: messenger.bus.default }
```

El orden en `services.yaml` define el orden de ejecución en la cadena.
`DispatchAfterCurrentBusMiddleware` debe ir PRIMERO (antes de
`TenantStampMiddleware` y `TenantContextMiddleware`) para que capture todos
los mensajes marcados con `DispatchAfterCurrentBus` stamp.

#### 2. `config/packages/messenger.yaml`
Añadir `retry_strategy` con `delay: 2000` (ms) al transporte `async`:

```yaml
        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 2000
                    multiplier: 2
            failed: 'doctrine://control_plane?queue_name=failed'
            sync: 'sync://'
```

(Actualmente `async: '%env(MESSENGER_TRANSPORT_DSN)%'` es forma corta sin
`retry_strategy`. Convertir a forma larga con `dsn:`.)

#### 3. `src/State/Sale/SaleProcessor.php`
En línea 371, envolver el dispatch con el stamp `DispatchAfterCurrentBus`:

```php
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

// ...

$this->messageBus->dispatch(
    new SendInvoiceMessage($invoiceResult['invoiceId']),
    [new DispatchAfterCurrentBusStamp()]
);
```

Añadir el `use` import al principio del archivo junto a los demás imports.

### Constraints
- Líneas ≤ 120 chars (PSR-12).
- No cambiar lógica de negocio de facturación.
- No tocar `SendInvoiceMessage`, `SendInvoiceMessageHandler`, `BillCreateUseCase`.
- El stamp correcto es `DispatchAfterCurrentBusStamp` (con sufijo `Stamp`),
  clase `Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp`.

### Acceptance
1. `services.yaml` tiene `DispatchAfterCurrentBusMiddleware` registrado antes de `TenantStampMiddleware`.
2. `messenger.yaml` transporte `async` tiene `retry_strategy.delay: 2000`.
3. `SaleProcessor::process()` dispatch incluye `[new DispatchAfterCurrentBusStamp()]`.
4. `make check` pasa verde.
```

---

#### TASK-008 Prompt

```
ORCHESTRATOR → agt-backend
TASK: TASK-008
SLICE: SLICE-003
INPUTS: [ART-S003-006-sale-processor-updated]
EXPECTED OUTPUT: [ART-S003-007-unit-tests]
DEADLINE GATE: make check must pass
---

## Task: Tests unitarios para el fix de SendInvoiceMessage

### Context
Añadir / actualizar tests unitarios de `SaleProcessor` cubriendo los siguientes
casos del flujo de auto-invoice.

### Directorio y convención
Buscar tests existentes de `SaleProcessor` en `tests/State/Sale/` y añadir los
test cases ahí, o crear `tests/State/Sale/SaleProcessorInvoiceDispatchTest.php`
si no existe un test unitario adecuado.

### Test cases requeridos

#### TC-1: `testDispatchNotCalledWhenCreateForSaleFails`
- Mock `InvoiceCreationService::createForSale()` para que retorne
  `['success' => false, 'message' => 'some error']`.
- Mock `MessageBusInterface` y afirmar que `dispatch()` **NO** es llamado.
- Configurar el `SaleProcessor` para que `$isNew=true`, `tipoId=2`,
  `shouldEmitInvoice=true`.

#### TC-2: `testDispatchIncludesDispatchAfterCurrentBusStamp`
- Mock `InvoiceCreationService::createForSale()` para que retorne
  `['success' => true, 'invoiceId' => 'test-uuid-123']`.
- Capturar el argumento pasado a `MessageBusInterface::dispatch()`.
- Afirmar que el segundo argumento (stamps array) contiene una instancia de
  `DispatchAfterCurrentBusStamp`.
- Afirmar que el primer argumento es una instancia de `SendInvoiceMessage`
  con `taxDocumentId === 'test-uuid-123'`.

### Implementation notes
- Usar `PHPUnit\Framework\TestCase`.
- Mockear todas las dependencias de `SaleProcessor` (es un constructor con
  muchos parámetros — usar `createMock()` para cada uno).
- Asegurarse de que los tests existentes en el archivo no se rompan.
- Líneas ≤ 120 chars.

### Acceptance
- TC-1 y TC-2 pasan con `vendor/bin/phpunit --filter SaleProcessorInvoiceDispatch`.
- `make check` verde.
```

---

### Wave 2 — Gate (agt-qa)

Runs only after Wave 1 gate passes.

---

#### TASK-009 Prompt

```
ORCHESTRATOR → agt-qa
TASK: TASK-009
SLICE: SLICE-003
INPUTS: [ART-S003-004, ART-S003-005, ART-S003-006, ART-S003-007]
EXPECTED OUTPUT: [ART-S003-008-gate-report]
DEADLINE GATE: make check must pass
---

## Task: Gate verification for SLICE-003

### Steps

1. Run `make check` (or equivalent: `docker compose exec -T web vendor/bin/grumphp run`).
2. Run `docker compose exec -T web vendor/bin/phpunit --exclude-group integration`
   to verify no regressions (exclude `InventoryServiceIntegrationTest` — known failure
   due to missing `bcmath` extension in Docker).
3. Verify acceptance criteria from SLICE-003.md:
   - AC1: `services.yaml` has `DispatchAfterCurrentBusMiddleware` before `TenantStampMiddleware`.
   - AC2: `messenger.yaml` transport `async` has `retry_strategy.delay: 2000`.
   - AC3: `SaleProcessor` dispatch includes `DispatchAfterCurrentBusStamp`.
   - AC4-5: Unit tests TC-1 and TC-2 pass.
   - AC6: `make check` green.
   - AC7: No regressions in existing `SaleProcessor` tests.

### Report
Return a gate report with PASS/FAIL per check.
```

---

## Gate Checkpoints

| Gate | Command      | After            | Required result | Actual result |
|------|--------------|------------------|-----------------|---------------|
| G1   | `make check` | Wave 1 complete  | All green       | ✅ PASS — 7/7 AC passed, phpstan/phpcs/phpmd/phpcsfixer clean, 22 tests green |
| G2   | `make check` | Wave 2 complete  | All green       | ✅ PASS — QA sign-off `9eaf4df9` |

---

## Artifact Register

| Artifact ID                        | Type           | File                                            | Status    |
|------------------------------------|----------------|-------------------------------------------------|-----------|
| ART-S003-001-messenger-config      | existing config | `config/packages/messenger.yaml`               | stable    |
| ART-S003-002-services-config       | existing config | `config/services.yaml`                         | stable    |
| ART-S003-003-sale-processor        | existing code   | `src/State/Sale/SaleProcessor.php`             | stable    |
| ART-S003-004-messenger-config-updated | config change | `config/packages/messenger.yaml`              | delivered |
| ART-S003-005-services-config-updated | config change | `config/services.yaml`                         | delivered |
| ART-S003-006-sale-processor-updated | code change   | `src/State/Sale/SaleProcessor.php`             | delivered |
| ART-S003-007-unit-tests            | test            | `tests/State/Sale/SaleProcessorInvoiceDispatchTest.php` | delivered |
| ART-S003-008-gate-report           | report          | QA sign-off: 7/7 AC passed                    | delivered |
