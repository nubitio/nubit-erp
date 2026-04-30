# Sales ↔ EInvoicing boundary

## Status

Active boundary policy.

## Intent

`src/Sales` owns the sale lifecycle: sale write orchestration, payment plan resolution, cash-session integration, inventory movement orchestration, sale status, and Sales API resources.

`src/EInvoicing` owns electronic invoicing concerns: invoice creation, SUNAT/Greenter integration, asynchronous tax-document sending, receipt/ticket printing, and electronic-document voiding.

Sales must not know EInvoicing internals. EInvoicing may adapt to Sales through an explicit Sales-owned port.

## Current seam

Sales-owned port:

- `App\Sales\Port\SaleInvoicingPortInterface`

Current EInvoicing adapter:

- `App\EInvoicing\Adapter\SaleInvoicingAdapter`

Symfony binding:

```yaml
App\Sales\Port\SaleInvoicingPortInterface: '@App\EInvoicing\Adapter\SaleInvoicingAdapter'
```

The adapter delegates to existing EInvoicing implementation details, including invoice creation, `SendInvoiceMessage`, sale voiding, receipt/ticket printing, and V2 sale loading.

## Dependency rules

### Sales

Allowed:

- `App\Sales\...`
- Sales-owned ports such as `SaleInvoicingPortInterface`
- upstream/shared contexts already allowed by `ContextBoundaryTest`

Forbidden:

- Direct imports from `App\EInvoicing\...`
- Direct construction or dispatch of EInvoicing messages such as `SendInvoiceMessage`
- Direct use of EInvoicing controllers/use cases from Sales entities, controllers, or state processors

Enforced by:

- `tests/Architecture/ContextBoundaryTest::testSalesDoesNotImportEInvoicingInternals`

### EInvoicing

Allowed:

- Implement `App\Sales\Port\SaleInvoicingPortInterface`
- Depend on current Sales entities/services where needed by the adapter or existing use cases

Preferred future direction:

- Reduce EInvoicing's dependency on full `SaleRecord` / `SaleItem` entities by introducing request DTOs or domain events.

## Current accepted compromise

`SaleInvoicingPortInterface` no longer exposes EInvoicing internals or accepts a full `SaleRecord` for invoice creation. Sales passes a sale id to the port; the EInvoicing adapter loads the sale representation it needs internally.

The adapter and some EInvoicing use cases still depend on Sales entities internally. That is acceptable for the current direction because the dependency is isolated on the EInvoicing side of the seam, not leaked back into Sales.

## Follow-up opportunities

1. Replace EInvoicing-internal `SaleRecord` usage with a DTO, for example `SaleInvoiceRequest` / `SaleInvoiceLine`.
2. Split explicit operations:
   - create invoice
   - dispatch invoice send
   - print receipt/ticket
   - void electronic document
3. Move EInvoicing use cases away from `SalesWriteGateway` where possible, using Sales-owned ports/events for sale status synchronization.
4. Consider moving API operation metadata for invoicing to dedicated API resources if `SaleRecord` metadata becomes too crowded.

## Validation

Useful checks after modifying this boundary:

```bash
docker compose exec -T web ./vendor/bin/phpunit tests/Architecture/ContextBoundaryTest.php
docker compose exec -T web ./vendor/bin/phpunit tests/EInvoicing/Adapter/SaleInvoicingAdapterTest.php
docker compose exec -T web php bin/console lint:container
docker compose exec -T web php bin/console debug:router --env=test | grep -E 'sale_make_invoice|sale_void|sale_print_invoice'
```
