# eFact Domain Context

## Product Model

### Tenant Runtime Config

Resolved tenant configuration consumed by frontend and application modules at runtime. It combines commercial entitlements, operational settings, tenant defaults, capabilities, counts and UI rules behind one interface.

Use this term instead of scattering feature flags or settings checks directly through screens.

### Capability

Operational behavior the product can enable for a tenant, such as `inventory.multi_warehouse`, `inventory.lot_tracking`, `cash.sessions` or `exports.excel`.

Capabilities describe behavior. Entitlements describe what the commercial plan allows.

### VerticalPreset

Versioned onboarding template that applies capabilities, defaults, menus, seed data and UI defaults for a target business type.

Examples (vertical axis only): `retail`, `pharmacy`, hardware store, butcher shop.

Implemented as the `App\Settings\Vertical\VerticalPresetInterface` seam (key, version,
label, settings, desired capabilities, single-stock-location flag). Presets are tagged
`app.vertical_preset` and resolved through `VerticalPresetRegistry`. `VerticalPresetApplier`
applies a preset to the current tenant (TenantSettings defaults + ensures a Default Stock
Location for single-location presets); it is idempotent and never deletes data. The console
command `tenant:apply-preset <key>` runs it per tenant. Concrete presets:
`RetailPreset` (key `retail`), `PharmacyPreset` (key `pharmacy`). Preset keys are
named after the vertical only — never the topology (no `single_store_*`).

The selected vertical is persisted on `TenantSettings.vertical` (written by the applier,
not by tenant PATCH). `TenantRuntimeConfigProvider` reads it as the source of truth for
`profile.vertical` / `profile.preset`, validating it against the registry (unknown →
`custom`). `simpleStoreMode` remains a separate, data-derived topology signal.

A preset encodes ONE axis only: the **vertical** (business type). The orthogonal
**topology/scale** axis (single vs multi store/warehouse) is NOT encoded in presets —
it is derived at runtime by `TenantRuntimeConfig` from the tenant's actual data
(`branchCount`, `warehouseCount`) and entitlements. This avoids a combinatorial
explosion of `single_store_pharmacy` / `multi_store_pharmacy` variants: one `pharmacy`
preset serves pharmacies of any size. `usesSingleStockLocation` only expresses a
preset's default topology intent for fresh tenants; runtime data always wins.

### Tienda Unica

Default product experience for a tenant with one physical store and one implicit stock location. Internally it still uses branch and warehouse records, but the user interface should not force the owner/cashier to think in branches or warehouses.

### Default Stock Location

The implicit branch and warehouse used when a tenant operates in Tienda Unica mode. Advanced tenants can still enable multi-branch or multi-warehouse capabilities.

## Pending Simplification Work

1. Hide warehouse management for Tienda Unica tenants.
   Use `TenantRuntimeConfig.ui.inventory.showWarehouseManagement` to hide warehouse admin routes and menu entries. Keep backend entities and schema unchanged.

2. Hide stock transfers for Tienda Unica tenants.
   Use `TenantRuntimeConfig.ui.inventory.showTransfers` to remove transfer entry points unless `inventory.multi_warehouse` is enabled.

3. Default warehouse in write forms.
   Stock entries, purchases and POS/sale forms should use `TenantRuntimeConfig.defaults.warehouse` when `showWarehouseSelector` is false. Users should not see an `Almacen` field in Tienda Unica mode.

4. Make menu resolution capability-aware.
   Capabilities decide which product modules are available; roles decide who can use the available modules. Preserve role-menu permissions as the advanced implementation.

5. Add a `SaleSubmitter` seam in POS.
   Online sales should submit directly by default. The outbox remains the offline adapter used on network failure or when an offline-first capability requires it.

6. Split the Sales/Billing mixed interface. (DONE)
   The broad `SaleInvoicingPortInterface` was replaced by narrower interfaces:
   `InvoiceCreatorInterface`, `SaleInvoicePrinterInterface`, `SaleVoiderInterface`
   and `RejectedDocumentReplacerInterface`. Invoice creation and rejected-document
   replacement now return HTTP-agnostic result DTOs (`InvoiceCreationResult`,
   `RejectedDocumentReplacementResult`); controllers own the HTTP response shaping.
   `SaleInvoicingAdapter` implements all four interfaces.
