# NB-16 · Core extension points

> Epic #326 · gap G8 · issue #342

A core **extension point** is an interface that a vertical (farmacia, ferretería,
restaurante, …) may replace to change a business rule, *without editing the
core*. Marking one is a single attribute on the interface in its owning context:

```php
#[AsExtensionPoint(
    id: 'inventory.stock_validation_policy',
    summary: 'Whether and how a sale line may draw stock the warehouse does not have.',
    default: TenantStockValidationPolicy::class,
    resolution: AsExtensionPoint::RESOLUTION_BINDING,
)]
interface StockValidationPolicyInterface { ... }
```

`App\Efact\Extension\ExtensionPointCatalog` discovers every marked interface;
`tests/Architecture/ExtensionPointCatalogTest` checks the `default` really
implements it, ids are unique, and this file lists every one.

## How a vertical specialises one

| `resolution`  | how the vertical overrides the default |
|---------------|----------------------------------------|
| `binding`     | the vertical's bundle/config aliases the interface to its own implementation, or decorates the bound service |
| `autowire`    | the vertical ships the only implementation, or a `#[AsDecorator]` on the interface |
| `resolver`    | a resolver already chooses per tenant capability / vertical — the vertical registers its implementation in that resolver's tagged iterator |

In every case the core keeps type-hinting the **interface**; nothing in `src/`
outside the seam's owning context changes.

## Registered extension points

| id | interface | default | resolution | specialises |
|----|-----------|---------|------------|-------------|
| `inventory.stock_validation_policy` | `App\Inventory\Core\Contract\StockValidationPolicyInterface` | `TenantStockValidationPolicy` | binding | whether a sale line may draw stock the warehouse does not have (pharmacy adds expiry/batch rules via `#[AsDecorator]` — see `App\Pharmacy\Policy\PharmacyStockValidationPolicy`) |
| `sales.detraction_calculator` | `App\Sales\State\DetractionCalculatorInterface` | `DetractionCalculator` | autowire | SPOT detraction thresholds and how they apply to a sale |

## Seams still to extract

NB-16 names six core seams. Two are interfaces today (above). The rest are still
concrete classes; they become extension points when the first vertical needs to
override them — extract the interface into the owning context, bind the current
class as the `default`, add `#[AsExtensionPoint]`, and this table + the test
pick it up automatically:

| seam | today | owning context |
|------|-------|----------------|
| `PriceCalculator` | price logic inside `InvoiceCalculator` / price-list providers | Billing / Inventory |
| `TaxCalculator` | IGV/ISC math in `InvoiceCalculator` | Billing |
| `InvoiceProcessor` | `App\Billing\Service\InvoiceCreationService` | Billing |
| `SaleValidator` | inline checks in `App\Sales\State\SaleProcessor` | Sales |
| `PaymentProvider` (POS tender) | `PaymentMethod` + settlement behaviour enum | Catalog / Sales |
