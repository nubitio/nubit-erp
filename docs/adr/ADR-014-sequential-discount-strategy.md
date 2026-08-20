# ADR-014: Sequential (Stacked) Discount Strategy — Line Then Sale-Level

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented

## Context

Reconstructed from `docs/PRD.md`'s "ADR-023: estrategia de descuentos" citation (the PRD's Billing section separately mentions "cálculo monetario exacto con `brick/math` y política de descuentos secuencial documentada en ADR-023" — that document never existed as a file; see ADR-006 for the numbering-collision context shared by all reconstructed ADRs).

A sale can have a discount on individual line items and a discount on the sale total. How these two combine changes the final amount: applying the sale-level discount to the original gross total gives a different (larger) total discount than applying it to the already-line-discounted subtotal.

## Decision

Discounts are **sequential/stacked**, applied in this order, confirmed in `App\Sales\State\SaleProcessor` (`src/Sales/State/SaleProcessor.php`):

1. **Line-level discount first.** Each sale line nets its own percentage discount independently: `itemTotal = grossAmount - discountAmount`, computed per line.
2. **Subtotal is the sum of already-discounted lines**, not the sum of gross line amounts.
3. **Sale-level discount applies on top of that subtotal**, not on the original gross total: `discountAmount = subtotal * discountRate; total = subtotal - discountAmount`.

All monetary math uses `Brick\Math\BigDecimal` via this project's `Money::` wrapper (the same primitive used throughout Billing and, since 2026-08-19, Payroll's quinta-categoría/liquidación calculations) rather than floats, for exact decimal arithmetic. `SaleDiscount` (`src/Sales/Entity/SaleDiscount.php`) models a discount with a `discountType` (default `PERCENT`); its full range of discount types was not audited as part of this reconstruction.

## Consequences

**Positive**
- Sequential application is predictable and auditable line-by-line: a cashier or auditor can verify the final total by re-deriving each step, rather than needing to know a combined formula.
- Exact decimal math (`BigDecimal`) avoids the classic float-rounding discrepancy between what's displayed and what's charged — important for a fiscal-document-emitting POS where the invoiced amount must match SUNAT's expectations to the cent.

**Negative**
- Sequential stacking produces a **smaller** total discount than applying both discounts independently to the gross amount would (e.g., 10% line + 10% sale-level ≠ 20% off gross; it's closer to 19%, since the second 10% applies to an already-reduced base). Anyone building a promotions/marketing feature on top of this needs to understand that discounts don't simply add.
- The specific order (line-level always before sale-level, never the reverse or configurable) is a hardcoded business rule in `SaleProcessor` — changing it for a specific promotion type would mean a code change, not a configuration toggle.

## Alternatives Considered

**Independent discounts, both computed against the original gross total** — not adopted; would allow a combined discount to (arguably confusingly) exceed the sum of the two individual percentages relative to what a customer would naively expect, and would make the subtotal→total step not reflect the line-level work already done.

**Configurable discount-stacking order** — not built. Would add real flexibility for promotions but also complexity and a new source of per-tenant behavior divergence; the current hardcoded order is simpler and matches how discounts are commonly explained to customers (item first, then a total-level coupon on what's left).
