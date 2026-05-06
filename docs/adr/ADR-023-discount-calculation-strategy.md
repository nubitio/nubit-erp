# ADR-023: Discount Calculation Strategy — Item-First Sequential Model and BigDecimal Precision Policy

**Status**: Accepted
**Date**: 2026-05-06
**Deciders**: adr-writer, equipo eFact
**Related Specs**: SPEC-006
**Supersedes**: —

---

## Context

SPEC-006 introduces per-item discounts across the eFact platform. Prior to this spec, only a single global discount could be applied at the document level. The new model requires two discount tiers:

1. **Line-level discount** (`discountPercent` per `SaleItem`) — reduces the unit price of a specific item before tax is computed.
2. **Document-level global discount** (`discountPercent` on the `Sale`) — reduces the net subtotal after all line discounts have been applied.

This raises two architectural questions that must be answered consistently across `InvoiceCalculator`, `SaleProcessor`, the SUNAT UBL 2.1 XML builder (`VoucherHydrator`), and all client applications (Android POS, web frontend):

**Question 1 — Calculation order**: Should the global discount be applied to the gross subtotal (before item discounts) or to the net subtotal (after item discounts)?

**Question 2 — Numeric precision**: Should monetary discount amounts be computed client-side (Android/web) or server-side? Which numeric type should be used to avoid floating-point drift on non-trivial tax rates and discount combinations?

SUNAT's UBL 2.1 specification (`PE:Invoice`) requires `AllowanceCharge` elements at both line level (`cac:InvoiceLine/cac:AllowanceCharge`) and document level (`cac:AllowanceCharge`). The SUNAT validation engine computes its own totals and rejects documents where the submitted amounts do not match within a tolerance of S/0.01. This makes numeric precision a compliance requirement, not merely a quality concern.

---

## Decision Drivers

- **SUNAT UBL 2.1 compliance**: `AllowanceCharge` amounts at line and document level must match SUNAT's own recomputation within S/0.01 tolerance.
- **Auditabilidad por línea**: per-item discounts must be traceable in the tax document line (`tax_document_line.discount_amount`) for audit and credit note purposes.
- **Consistencia con SAP/Odoo/SUNAT estándar**: the sequential model (item-first, then global) is the established convention in ERP and SUNAT-certified systems operating in Peru.
- **Eliminación de errores de punto flotante**: PHP native `float` and JavaScript `number` both use IEEE 754 double precision, which produces rounding errors on amounts like `100 × 0.18 × 0.85` that accumulate across lines.
- **Single source of truth**: discount amounts must be derived in one place (the server) to prevent discrepancies between Android, web, and backend calculations.
- **Reversibilidad de crédito**: credit notes (`Nota de Crédito`) reference original line amounts; line-level discount amounts must be stored and stable.

---

## Considered Options

### Decisión 1 — Orden de aplicación de descuentos

1. **Sequential model: item discount first, then global discount on net subtotal** — each line's `discountAmount` is computed from its gross amount; the global discount is then applied to the sum of net line amounts.
2. **Parallel model: both discounts applied independently to gross subtotal** — item discount reduces each line; global discount is applied to the original gross subtotal, not the post-item-discount subtotal.
3. **Global-only model: single document-level discount, no per-item discount** — the existing pre-SPEC-006 behaviour; item-level discounts are not supported.

### Decisión 2 — Precisión numérica y origen del `discountAmount`

1. **Server-side derivation with `Brick\Math\BigDecimal` HALF_UP, 2 decimal places** — API accepts only `discountPercent`; `discountAmount` is always computed by `InvoiceCalculator` using `BigDecimal`.
2. **Client-side calculation, client sends `discountAmount`** — Android/web compute the amount and send it as an authoritative field in the request body.
3. **Native PHP `float` arithmetic, server-side** — server computes amounts using PHP's built-in float type.

---

## Decision Outcome

### D1 — Sequential discount model (item-first, then global)

**Chosen option**: **Sequential model: item discount first, then global discount on net subtotal**

**Rationale**: This is the model mandated by SUNAT's UBL 2.1 schema and implemented by SAP, Odoo, and every SUNAT-certified ERP operating in Peru. In the sequential model, the global `AllowanceCharge` at document level references the net subtotal (after line discounts), which is the value SUNAT uses as the base for IGV computation. Applying the global discount to the gross subtotal (parallel model) would produce a different IGV base and cause SUNAT rejection. The global-only model is rejected because it loses the per-item audit trail required for credit notes and the `tax_document_line.discount_amount` field.

### D2 — Server-side derivation with `Brick\Math\BigDecimal`

**Chosen option**: **Server-side derivation with `Brick\Math\BigDecimal` HALF_UP, 2 decimal places**

**Rationale**: Floating-point arithmetic in PHP `float` and JavaScript `number` produces non-deterministic rounding on amounts that involve multiple multiplications (e.g. `unitPrice × quantity × (1 − discountPercent) × igvRate`). `Brick\Math\BigDecimal` provides arbitrary-precision decimal arithmetic with explicit rounding mode. HALF_UP at 2 decimal places matches SUNAT's own rounding convention. Client-side calculation is rejected because Android and web use different numeric libraries and rounding implementations, making it impossible to guarantee that the client-submitted `discountAmount` matches the server's recomputation — which would cause SUNAT XML validation failures.

---

## Pros and Cons of the Options

### D1 — Orden de aplicación

#### Sequential model (item-first, then global on net subtotal) ✅

**Pros**:
- Matches SUNAT UBL 2.1 `AllowanceCharge` semantics at both line and document level.
- Consistent with SAP, Odoo, and all SUNAT-certified ERPs operating in Peru.
- Per-item `discountAmount` is stored in `tax_document_line` for credit note and audit purposes.
- IGV base is unambiguous: it is the net subtotal after all line discounts.

**Cons**:
- Slightly more complex calculation pipeline than a single global discount.
- The effective combined discount rate is not simply `itemDiscount + globalDiscount`; this must be documented for support staff.

---

#### Parallel model (both discounts on gross subtotal)

**Pros**:
- Simpler mental model: each discount is independent of the other.

**Cons**:
- Non-standard: not how SUNAT, SAP, or Odoo define document-level `AllowanceCharge`.
- Produces a different IGV base than SUNAT expects; causes XML validation rejection.
- Ambiguous: if item discount is 10% and global is 5%, the effective discount depends on interpretation.

---

#### Global-only model (pre-SPEC-006 behaviour)

**Pros**:
- No change to existing code.

**Cons**:
- Does not satisfy SPEC-006 requirements.
- No per-item discount audit trail.
- Cannot support credit notes that reference a specific line's discounted amount.

---

### D2 — Precisión numérica

#### Server-side `Brick\Math\BigDecimal` HALF_UP ✅

**Pros**:
- Arbitrary-precision decimal arithmetic; no IEEE 754 rounding drift.
- HALF_UP at 2 decimal places matches SUNAT's rounding convention.
- Single source of truth: all clients display the server-derived amount.
- `discountAmount` in API responses is always authoritative and consistent.

**Cons**:
- `brick/math` is an additional Composer dependency (already present in the project via `InvoiceCalculator`).
- Clients cannot pre-validate the exact amount before submitting; they can only display an approximation.

---

#### Client-side calculation, client sends `discountAmount`

**Pros**:
- Client can display the exact amount it will send before the request is made.

**Cons**:
- Android (Kotlin) and web (JavaScript) use different numeric libraries with different default rounding.
- Server must either trust the client amount (risk of SUNAT mismatch) or recompute and reject mismatches (complex validation logic).
- Any client bug propagates directly into SUNAT XML and causes rejection.
- Violates the single-source-of-truth principle for financial calculations.

---

#### Native PHP `float`, server-side

**Pros**:
- No additional dependency; uses PHP built-ins.
- Familiar to all PHP developers.

**Cons**:
- IEEE 754 double precision produces rounding errors on non-trivial discount + tax combinations (e.g. `99.99 × 0.15 × 1.18` does not round cleanly to 2 decimal places).
- Errors accumulate across lines; a 10-line invoice can have a total off by S/0.01, causing SUNAT rejection.
- Already rejected in `InvoiceCalculator` (see ADR-012 context); inconsistent to use `float` for discounts while using `BigDecimal` for taxes.

---

## Consequences

### Positive
- `InvoiceCalculator` and `SaleProcessor` share a single, unambiguous calculation order: line discounts → net subtotal → global discount → IGV base → IGV → total.
- SUNAT XML `AllowanceCharge` amounts at line and document level are always consistent with the server's `BigDecimal` computation.
- `tax_document_line.discount_amount` is populated for every line with a non-zero discount, enabling accurate credit notes.
- Clients (Android POS, web) are freed from implementing financial arithmetic; they send only `discountPercent` and display the server-derived amounts.
- The precision policy is uniform: `BigDecimal` HALF_UP 2dp is used for all monetary calculations in `InvoiceCalculator`, not just for discounts.

### Negative
- The API contract is asymmetric: `discountPercent` is accepted as input but `discountAmount` is read-only in responses. This must be clearly documented in the OpenAPI spec.
- Clients cannot show the exact final amount before submitting the sale; they must either approximate or make a preview request.
- The effective combined discount is not additive (`itemDiscount% + globalDiscount% ≠ effectiveDiscount%`); support documentation must explain this.

### Risks
- **SUNAT schema change alters rounding convention**: if SUNAT changes from HALF_UP to HALF_EVEN, all `BigDecimal` calls in `InvoiceCalculator` must be updated. — Mitigation: `RoundingMode` is injected as a constant in `InvoiceCalculator`; a single change propagates everywhere.
- **Client displays approximated amount that differs from server by S/0.01**: user sees S/10.00 on screen but invoice shows S/9.99. — Mitigation: clients must fetch the server-computed preview before displaying the final total; document this in the Android and web integration guides.
- **`discountPercent` precision**: if a client sends `discountPercent` with more than 4 decimal places, rounding of the percent itself can cause unexpected amounts. — Mitigation: API validation rejects `discountPercent` values with more than 4 decimal places (`Assert\Range` + `Assert\Regex`).

---

## Implementation Notes

- `InvoiceCalculator::calculateLine(SaleItem $item): LineResult` computes `discountAmount = unitPrice × quantity × discountPercent / 100` using `BigDecimal`, then `netAmount = grossAmount − discountAmount`.
- `InvoiceCalculator::calculateDocument(Sale $sale, LineResult[] $lines): DocumentResult` computes `netSubtotal = sum(lineNetAmounts)`, then `globalDiscountAmount = netSubtotal × globalDiscountPercent / 100`, then `igvBase = netSubtotal − globalDiscountAmount`.
- `SaleProcessor` must call `InvoiceCalculator` in the same order; it must not recompute amounts independently.
- `VoucherHydrator` reads pre-computed amounts from `TaxDocument` / `TaxDocumentLine`; it must not recompute them.
- All `BigDecimal` divisions use `RoundingMode::HALF_UP` and scale 2. Intermediate calculations may use scale 10 before the final `toScale(2, RoundingMode::HALF_UP)`.
- The API resource `SaleItem` exposes `discountPercent` (writable, 0–100, max 4 decimal places) and `discountAmount` (read-only, derived). The OpenAPI description must state: _"discountAmount is computed server-side and must not be sent in write requests."_
- Android and web clients must treat `discountAmount` in API responses as the authoritative value for display and receipt printing.

---

## References

- [SUNAT — Estructura UBL 2.1 para Factura Electrónica](https://cpe.sunat.gob.pe/sites/default/files/inline-files/Manual_comprobante_pago_electronico_v2.pdf) — `AllowanceCharge` at line and document level
- [brick/math — Arbitrary-precision arithmetic for PHP](https://github.com/brick/math) — `BigDecimal` with `RoundingMode::HALF_UP`
- ADR-012 — Electronic Invoicing with greenter/SUNAT: establishes `InvoiceCalculator` and `brick/math` as the precision foundation
- ADR-022 — Feature Gates: SPEC-005 context; per-item discounts are gated behind the `item_discounts` feature flag (SPEC-006)
