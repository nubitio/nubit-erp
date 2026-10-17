# Recurring Billing Plan

> **Estado al 2026-10-16:** este documento empezó como propuesta; la funcionalidad principal está implementada en `src/Billing/Recurring/` y `assets/features/subscriptions/`. El sistema tiene planes de suscripción, suscripciones y líneas, historial de ejecuciones, generación manual/programada de ventas y notificación de ventas recurrentes próximas. El acceso comercial usa entitlement `recurring_billing` y capability `billing.recurring`; la UI/API usan permisos `subscriptions.*`, no el flag/permission set propuesto más abajo. No tomar las secciones de diseño como descripción exacta del API/UI actual ni asumir que las preguntas diferidas siguen abiertas; contrastar con clases y rutas antes de modificar.

## Goal

Add an optional recurring billing module for tenants that sell periodic services, such as hosting, support, maintenance, or subscriptions. The module should generate sales and electronic documents from customer subscriptions without relying on the existing sale duplication flow.

## Scope

The feature is tenant opt-in and should be disabled by default. Tenants that do not use recurring billing should not see the module or pay any operational complexity cost.

The first production slice should support customer subscriptions, recurring service lines, scheduled generation, manual generation, receivables tracking, and manual payment reminders.

## Product Decisions

- The module name in the UI is `Recurrentes`.
- The primary domain concept is `Suscripcion` for a customer subscription.
- The sales list should not expose `Duplicar` as the recurring-billing workflow.
- Recurring billing is controlled by a tenant feature flag, initially `tenant_settings.recurring_billing_enabled`, default `false`.
- V1 frequencies are `monthly` and `yearly`.
- A subscription can have multiple lines.
- Each line must reference an existing product or service from the catalog.
- The line price is copied from the catalog when added, then stored as a fixed subscription line price.
- Subscription changes only affect future generated periods.
- Generated sales must not require an open cash register.
- Services should not decrement stock; the existing product/service distinction should be respected.
- IGV must always come from tenant settings at generation time, not from derived amounts.

## UI Shape

The module has three tabs:

- `Servicios`: manage customer subscriptions and subscription lines.
- `Emisiones`: inspect generated sales/documents by billing period.
- `Pendientes`: manage receivables, payments, and manual reminders.

The first shell can show empty states for the three tabs, but functional work should start with `Servicios`.

## Subscription Lifecycle

Subscription statuses:

- `draft`: not yet active and should not be generated automatically.
- `active`: eligible for scheduled generation.
- `paused`: temporarily excluded from generation.
- `cancelled`: permanently stopped.

Required dates:

- `starts_on`: required.
- `ends_on`: optional.
- `next_run_date`: used by the daily scheduler to find due subscriptions.

Billing period mode:

- `current`: generate for the period containing the run date.
- `previous`: generate for the previous period.
- `next`: generate for the next period.

## Persistence Model

Suggested tenant tables:

- `customer_subscription`
- `customer_subscription_line`
- `customer_subscription_run`

Use `customer_subscription*` naming to avoid confusion with platform/SaaS subscription concepts in the control plane.

`customer_subscription` should store:

- Customer reference.
- Status.
- Frequency.
- Start/end dates.
- Next run date.
- Billing period mode.
- Branch and warehouse references.
- Document policy.
- Payment behavior.
- Optional notes/template fields.

`customer_subscription_line` should store:

- Subscription reference.
- Product/service reference.
- Description.
- Quantity.
- Unit price.
- Tax classification.
- Sort order.

`customer_subscription_run` should store:

- Subscription reference.
- Period start and end.
- Run date.
- Status.
- Generated sale reference.
- Generated tax document reference when applicable.
- Error message for failed runs.
- Source, such as `scheduler` or `manual`.

Run statuses:

- `pending`
- `generated`
- `failed`
- `skipped`

Idempotency requirement:

- Enforce uniqueness by `(subscription_id, period_start, period_end)`.
- Generation must be safe to retry.

## Generation Behavior

Generation creates a sale with origin/channel `recurring_billing` and a link back to the subscription/run.

The generated sale should include automatic period text in the sale lines or notes, based on templates. Example: `Servicio hosting - Mayo 2026`.

The system needs two generation paths:

- Daily scheduler: processes active subscriptions where `next_run_date <= today`.
- Manual action: lets a user generate now for a selected date/period.

## Document Policy

Document policy controls when an electronic document is generated:

- `on_generation`: create the document when the recurring sale is generated.
- `on_payment`: create the document only after the sale is fully paid.

For `on_payment`, generation is allowed only when `sale.balance_due = 0`.

If the policy is `on_generation`, the document enters the normal pending/send flow according to tenant billing settings. Do not add a special SUNAT hold state for V1.

## Payment Behavior

Payment behavior controls the sale payment state after generation:

- `pending`: default; sale remains receivable.
- `mark_paid`: immediately marks the sale as paid using the configured payment path.

The `Pendientes` tab should rely on existing sale receivable data:

- `sale.balance_due`
- `sale_payments`

## Reminders

V1 reminders are manual only.

The receivables tab should provide:

- A modal with an editable message.
- Copy-to-clipboard.
- WhatsApp link.
- Printable payment order.

Automated reminders are out of scope for V1.

## Permissions

Planned permissions:

- `recurring_billing.view`
- `recurring_billing.manage`
- `recurring_billing.generate`
- `recurring_billing.collect`

These should be mapped to the real application permission/menu model when implementation starts. Do not add a separate permission system unless the existing model cannot represent these actions.

## Implementation Slices

1. Feature gate and module shell.
2. Entities, migrations, repositories, and API resources.
3. `Servicios` UI CRUD for subscriptions and lines.
4. Idempotent generation service with tests.
5. Scheduler command and manual generation endpoint.
6. `Emisiones` tab with run history and generated sale/document links.
7. `Pendientes` tab with receivables and manual reminders.
8. Permission mapping and role configuration.

## Validation Expectations

- Unit tests for period calculation and idempotency.
- Integration tests for sale generation.
- Tests for document policy behavior.
- Tests that disabled tenants do not see or access the module.
- Frontend typecheck and focused lint for module pages.
- Backend PHPStan, PHPCS, PHPUnit, and migration validation.

## Deferred Questions

- Exact role/menu permission storage for the four planned permissions.
- Whether `mark_paid` needs explicit payment method configuration per subscription.
- Whether period descriptions should be tenant-editable templates or fixed strings in V1.
- Whether yearly subscriptions can specify an anniversary month/day independent of `starts_on`.
