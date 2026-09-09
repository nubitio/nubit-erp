# NB-20 · Structured operation log with a cross-context `correlation_id`

> Epic #326 · gap G9 · issue #346

## Goal

Follow one business operation — say a credit sale that fans out to inventory,
electronic invoicing (CPE) and caja — across bounded contexts and across the
async worker boundary, by querying a single id.

## Field set

Every record on the `operation` monolog channel (and, in practice, every
structured record the app emits) carries:

| field            | source                                                                   | added by |
|------------------|--------------------------------------------------------------------------|----------|
| `module`         | owning bounded context, lower-case (`sales`, `inventory`, `billing`, …)   | `OperationLog::record()` |
| `operation`      | dotted verb phrase (`sale.created`, `receivable.collected`, `stock.drawn`)| `OperationLog::record()` |
| `entity_id`      | primary key of the entity acted on                                        | `OperationLog::record()` |
| `user_id`        | acting principal (username / API-key id / CLI command)                    | `OperationContextProcessor` |
| `correlation_id` | stable alias of `request_id` — one value for the whole fan-out            | `OperationContextProcessor` |
| `tenant_id`      | tenant numeric id                                                         | platform `TenantLogProcessor` |
| `request_id`     | inbound request / worker message id                                       | platform `TenantLogProcessor` |
| `trace_id`, `span_id` | active OpenTelemetry span                                           | platform `TenantLogProcessor` |
| `timestamp`      | record datetime                                                           | monolog |

`OperationLog` is the operational log **stream** (stderr JSON in prod, shipped to
the OTel collector). It is not the audit trail — use `App\Efact\Audit\AuditLogger`
when an event must be persisted to `nubit_audit_log` for compliance.

## How `correlation_id` propagates between contexts

`correlation_id` is the platform `request_id`. It is set once per entrypoint and
then carried, unchanged, through every hop:

1. **HTTP / CLI entrypoint** — the platform tenant listener puts `request_id`
   (and the actor) on `Nubit\Platform\Tenant\Context\TenantContext`.
2. **Message dispatch** — `Nubit\Platform\Messenger\TenantStampMiddleware` copies
   `requestId` and the actor onto every `Envelope` as a `TenantStamp` /
   `ActorStamp` when one is not already present.
3. **Worker** — `Nubit\Platform\Messenger\TenantContextMiddleware` rehydrates
   `TenantContext` from those stamps before the handler runs, so a message
   handled minutes later on another process still logs the same
   `correlation_id`, and any messages *it* dispatches inherit the stamp.
4. In parallel, W3C `traceparent` is propagated by
   `Nubit\Platform\Messenger\TracingMiddleware` for OTel span linking
   (`trace_id`).

`App\Accounting\Message\EconomicEvent` is routed `sync`, so the ledger posting
for a sale runs inside the originating request and shares its `correlation_id`
without a stamp round-trip. The caja movement for a receivable collection is a
raw-SQL write in the same request — same `correlation_id`.

## Worked example — one credit sale

```
correlation_id=req-7f3a…  module=sales      operation=sale.created           entity_id=1042
correlation_id=req-7f3a…  module=inventory  operation=stock.drawn            entity_id=1042
correlation_id=req-7f3a…  module=billing    operation=cpe.dispatched         entity_id=1042
correlation_id=req-7f3a…  module=sales      operation=receivable.collected   entity_id=1042
```

`grep correlation_id=req-7f3a` (or the equivalent OTel/Loki filter) returns the
whole operation.

## Adding an operation log to a flow

Inject `App\Efact\Observability\OperationLog` and call `record()` after the state
change is durable:

```php
$this->operationLog->record('sales', 'receivable.collected', $sale->getId(), [
    'sale_payment_id' => $payment->getId(),
    'amount'          => $amount,
]);
```

No configuration is needed: the `operation` channel is declared in
`config/packages/monolog.yaml`, `$operationLogger` is bound in
`config/services.yaml`, and `OperationContextProcessor` is autoconfigured via
`#[AsMonologProcessor]`.
