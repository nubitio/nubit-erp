# SLICE-003 — Corregir `SendInvoiceMessage` falla con "Invoice not found"

| Field        | Value                                      |
|--------------|--------------------------------------------|
| id           | SLICE-003                                  |
| status       | approved                                   |
| created_at   | 2026-03-02                                 |
| updated_at   | 2026-03-02                                 |
| author       | agt-architect                              |

---

## 1. Purpose & Scope

### In scope
- Eliminar la condición de carrera entre `InvoiceCreationService::createForSale()`
  (que inserta el `tax_document` y hace `commit`) y el worker de Symfony Messenger
  que procesa `SendInvoiceMessage` antes de que la fila sea visible en su
  conexión de base de datos.
- Garantizar que cuando `BillCreateUseCase` ejecuta
  `$taxDocumentRepository->find($taxDocumentId)`, el registro ya sea visible
  (independientemente del aislamiento MySQL / snapshot del worker).
- Añadir un delay de reintento configurable en el transporte `async` para
  mitigar la carrera en caso de que la fila todavía no sea visible.

### Out of scope
- Cambios en la lógica de negocio de facturación electrónica (SUNAT, Greenter).
- Cambios en el transporte de Messenger (se mantiene `async`).
- Refactoring de `InvoiceCreationService` más allá del mínimo necesario.
- Cambios en la gestión de tenants / contexto multi-tenant del worker.

---

## 2. Root-cause analysis

### Descripción del bug

Cuando `SaleProcessor` crea un comprobante electrónico vía
`InvoiceCreationService::createForSale()`, el flujo es:

1. `createForSale()` abre una transacción DBAL, inserta `tax_document` + líneas +
   evento, y hace **`$connection->commit()`** (línea 227).
2. `SaleProcessor` despacha `new SendInvoiceMessage($taxDocumentId)` al bus
   (línea 371) — el mensaje va al transporte **`async`** (cola Doctrine/RabbitMQ).
3. El worker de Messenger es un **proceso separado** con su **propia conexión de BD**.
   Bajo el aislamiento `REPEATABLE READ` de MySQL (el default), si la conexión del
   worker ya tenía un snapshot activo *antes* del `commit` del HTTP request, la
   fila `tax_document` recién insertada **no es visible** para esa conexión.
4. `BillCreateUseCase` llama a `$taxDocumentRepository->find($taxDocumentId)`
   (línea 45) → retorna `null` → lanza `ServiceException('Invoice not found')`.
5. Messenger reintenta 3 veces sin delay, las 3 veces fallan por la misma causa,
   y el mensaje termina en la cola `failed`.

### Evidencia adicional

- El `retryCount: 3` en los logs indica fallo consistente, no esporádico.
- El `SendInvoiceMessage` **no está listado en `routing`** de `messenger.yaml`;
  se enruta mediante el atributo `#[AsMessage(transport: 'async')]`. Esto es
  correcto, pero no hay `retry_strategy` configurada → los reintentos ocurren
  prácticamente al instante, sin dar tiempo al worker para que la fila sea visible.
- Los campos `tenant: null` en los logs de error sugieren que el worker puede
  estar operando sin contexto de tenant; esto es un problema separado y está
  **fuera del scope** de esta slice.

### Solución

**Opción A (preferida) — Delay en el reintento + dispatch después del flush:**

1. Configurar una `retry_strategy` con un `delay` inicial de al menos 1 segundo
   en el transporte `async` en `messenger.yaml`. Esto da tiempo al worker para
   que la fila ya sea visible cuando reintente.
2. Asegurarse de que `messageBus->dispatch()` ocurra **después** de que Doctrine
   haga `flush()` y de que cualquier transacción externa ya esté comprometida.
   En el código actual `InvoiceCreationService` usa DBAL directamente y hace su
   propio `commit`, por lo que el dispatch en `SaleProcessor` línea 371 ya está
   después del commit de `createForSale`. ✅ Sin embargo, si `SaleProcessor`
   mismo está envuelto en una transacción de Doctrine, el flush/commit de esa
   transacción podría ocurrir *después* del dispatch.

3. Verificar si `SaleProcessor::process()` tiene una transacción de Doctrine
   envolvente. Si la hay, mover el dispatch al `postFlush` o usar
   `DispatchAfterCurrentBusMiddleware` / `DoctrineTransactionMiddleware` de
   Symfony Messenger para que el mensaje se encole solo después del commit.

**Opción B (fallback) — `DoctrineTransactionMiddleware`:**

Registrar `DoctrineTransactionMiddleware` en la cadena del bus. Este middleware
envuelve el dispatch en la misma transacción Doctrine activa y solo encola el
mensaje cuando esa transacción hace commit. Esto elimina la carrera de raíz.

**Decisión:** El transporte `async` usa **Redis** (no Doctrine), por lo que
`DoctrineTransactionMiddleware` no aplica. La solución correcta para Redis es:

1. **`DispatchAfterCurrentBusMiddleware`** — registrar el middleware en el bus
   `default` en `services.yaml`. Luego en `SaleProcessor`, envolver el dispatch
   con el stamp `DispatchAfterCurrentBus`. Esto le indica al bus que **difiera el
   encolado real en Redis** hasta que el pipeline del bus actual (el HTTP request)
   haya terminado completamente — es decir, después de que todos los `flush()`
   y commits de la request estén hechos.
2. **`retry_strategy.delay: 2000`** — añadir un delay de 2 segundos entre
   reintentos en el transporte `async` como red de seguridad.

| Archivo | Cambio |
|---------|--------|
| `config/packages/messenger.yaml` | Añadir `retry_strategy.delay: 2000` al transporte `async`. |
| `config/services.yaml` | Registrar `DispatchAfterCurrentBusMiddleware` en el bus `messenger.bus.default`. |
| `src/State/Sale/SaleProcessor.php` | Añadir stamp `new DispatchAfterCurrentBus()` al dispatch de `SendInvoiceMessage`. |

---

## 3. Domain context

- **Bounded context primario:** E-Invoicing / Billing
- **Bounded context secundario:** Sales (POS)
- **Entidades involucradas:** `TaxDocument` (tabla `tax_document`), `SendInvoiceMessage`, `BillCreateUseCase`

---

## 4. User story

> Como cajero que registra una venta con emisión automática de comprobante
> electrónico, quiero que el comprobante sea enviado a SUNAT sin errores,
> para que la factura electrónica quede registrada correctamente en el primer
> intento.

---

## 5. Comportamiento esperado (antes → después)

### Escenario A — Venta con cliente RUC (factura) y `AUTO_INVOICE=true`
- **Antes:** `InvoiceCreationService` crea el `tax_document`, `SendInvoiceMessage`
  se encola, el worker lo consume antes de que la fila sea visible → `"Invoice not
  found"` → 3 reintentos → mensaje en `failed`. ❌
- **Después:** El mensaje se encola solo después de que la transacción Doctrine
  hace commit (via `DoctrineTransactionMiddleware`) → worker encuentra la fila →
  comprobante enviado a SUNAT exitosamente. ✅

### Escenario B — Venta sin `AUTO_INVOICE` (comportamiento sin cambio)
- No se despacha `SendInvoiceMessage`. Sin cambio. ✅

### Escenario C — Worker con snapshot obsoleto (carrera residual)
- Con el middleware en su lugar, la carrera está eliminada. El `delay: 2000ms`
  actúa como red de seguridad para cualquier otro camino de dispatch futuro. ✅

---

## 6. API surface

Sin cambios en contratos de API. El response de `POST /api/sales` no cambia.
El procesamiento del comprobante es asíncrono.

---

## 7. Domain events

Ninguno nuevo.

---

## 8. Data requirements

No se requieren migraciones.

---

## 9. Dependent artifacts

| Artifact | Tipo | Estado |
|----------|------|--------|
| `config/packages/messenger.yaml` | Config existente | Necesita `retry_strategy` |
| `config/services.yaml` | Config existente | Necesita `DispatchAfterCurrentBusMiddleware` |
| `SendInvoiceMessage` | Código existente | Sin cambio |
| `SendInvoiceMessageHandler` | Código existente | Sin cambio |
| `BillCreateUseCase` | Código existente | Sin cambio |
| `SaleProcessor` | Código existente | Necesita stamp `DispatchAfterCurrentBus` |

---

## 10. Sub-tasks

| # | Descripción | Agente |
|---|-------------|--------|
| T1 | Registrar `DispatchAfterCurrentBusMiddleware` en `services.yaml`; añadir `retry_strategy.delay: 2000` al transporte `async` en `messenger.yaml`; añadir stamp `DispatchAfterCurrentBus` al dispatch en `SaleProcessor` | `agt-backend` |
| T2 | Test unitario: `SaleProcessor` verifica que `dispatch` no se llama si `createForSale` falla; test que confirma que el stamp `DispatchAfterCurrentBus` está presente en el envelope despachado | `agt-backend` |
| T3 | Gate: `make check` verde; verificar en logs de staging que `SendInvoiceMessage` ya no aparece en la cola `failed` | `agt-qa` |

---

## 11. Acceptance criteria

1. `config/services.yaml` registra `DispatchAfterCurrentBusMiddleware` en el
   bus `messenger.bus.default`.
2. El transporte `async` tiene `retry_strategy.delay: 2000` (ms) configurado en
   `messenger.yaml`.
3. `SaleProcessor` añade el stamp `new DispatchAfterCurrentBus()` al dispatch de
   `SendInvoiceMessage`.
4. Un test unitario verifica que cuando `createForSale` retorna `success: false`,
   el bus **no** despacha `SendInvoiceMessage`.
5. Un test unitario verifica que el envelope despachado lleva el stamp
   `DispatchAfterCurrentBus`.
6. `make check` pasa en verde (phpstan, phpcs, phpmd, phpcsfixer, phpunit
   excluyendo `InventoryServiceIntegrationTest`).
7. No hay regresiones en los tests existentes de `SaleProcessor`.

---

## 12. Open questions / risks

| # | Pregunta | Riesgo |
|---|----------|--------|
| Q1 | ¿`DispatchAfterCurrentBusMiddleware` ya está disponible en la versión de `symfony/messenger` instalada? Verificar en `composer.json`. | Bajo — disponible desde Messenger 5.1. |
| Q2 | ¿El stamp `DispatchAfterCurrentBus` funciona si el dispatch se hace dentro de un `try/catch` en `SaleProcessor`? Sí — el middleware lo recoge del envelope en el pipeline del bus, independientemente del catch. | Bajo |
| Q3 | Los logs muestran `tenant: null` en el worker. ¿El worker hereda el contexto de tenant? Esto es fuera de scope pero podría causar otros errores en `BillCreateUseCase` (ej. `CompanyRepository::findOneBy` retorna null). | Medio — investigar en slice futura si el fix de esta slice no resuelve todos los fallos. |
