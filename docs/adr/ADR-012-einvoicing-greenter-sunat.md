# ADR-012 — Electronic Invoicing: greenter/core for SUNAT UBL 2.1

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact must emit SUNAT-compliant electronic tax documents (comprobantes electrónicos) on behalf of Peruvian tenants. SUNAT's e-invoicing system requires:

1. **UBL 2.1 XML** — Documents must be formatted as Universal Business Language (UBL) 2.1 XML with SUNAT's local extensions.
2. **Digital signature** — XML documents must be signed with the tenant's digital certificate (PFX/P12) using XML-DSig.
3. **Web service submission** — Signed XML is wrapped in a SOAP envelope and submitted to SUNAT's web services (`https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService`).
4. **CDR (Constancia de Recepción)** — SUNAT returns a ZIP containing a CDR XML confirming acceptance or rejection.
5. **Consult API** — Tenants can query the status of a previously submitted document via `greenter/consulta-cpe`.
6. **Document types supported:** Factura (01), Boleta (03), Nota de Crédito (07), Nota de Débito (08), Resumen Diario (RA), Comunicación de Baja (RC).
7. **Detraction support** — High-value transactions require detraction fields in the XML.

**Library options evaluated:**

| Library | Notes |
|---------|-------|
| **Custom UBL XML generation** | Full control; extreme maintenance burden (SUNAT updates XML schemas periodically); error-prone |
| **`greenter/greenter`** (old, v2) | Older version; still maintained; some tenants using it |
| **`greenter/core` v5** (chosen) | Current recommended version; UBL 2.1 + SUNAT extensions; active maintenance; used by thousands of Peruvian systems |
| **`nubefact`/`efact.pe` third-party API** | Delegates signing and submission to a third party; adds cost and dependency; reduces control over failure modes |

`greenter/core` is the de facto standard library for SUNAT electronic invoicing in the PHP ecosystem. Using it gives access to:
- Pre-built UBL 2.1 builders for all document types.
- XML-DSig signing with tenant PFX certificate.
- SOAP client for SUNAT web service submission.
- CDR parsing.

`greenter/consulta-cpe` provides the SUNAT consult API client for CDR status lookup.

---

## Decision

Use **`greenter/core` 5.x** for UBL 2.1 XML generation and signing, and **`greenter/consulta-cpe`** for CDR status consultation.

### Invoice Creation Flow

```
POST /api/sales/{id}/invoice
  → InvoiceCreationService::createForSale()
    → Determine document type: FACTURA (11-digit RUC) or BOLETA (8-digit RUC)
    → Fetch series from VoucherSeries (branch + document type)
    → Generate sequential number (FOR UPDATE lock)
    → Insert into tax_document + tax_document_line
    → Insert initial tax_document_event (issued, pending)
    → Return tax_document_id
```

### Invoice Send Flow (async via Symfony Messenger)

```
POST /api/tax-documents/{id}/send
  → Dispatch SendInvoiceMessage to Messenger async transport
  → Handler:
    → VoucherHydrator::hydrate() builds greenter Document object from TaxDocument entity
    → greenter signs XML with tenant's PFX certificate
    → SOAP client submits to SUNAT web service
    → Parse CDR response
    → Update tax_document: sunatStatus, sunatMessage, cdrPath
    → Upload CDR ZIP to S3 (Flysystem)
    → Insert tax_document_event (sent/accepted/rejected)
    → If accepted: publish Mercure update
    → If rejected: evaluate circuit-breaker
```

### VoucherHydrator

`VoucherHydrator` maps eFact's `TaxDocument` entity to greenter's object graph:

```php
// src/Billing/Invoice/Service/VoucherHydrator.php
class VoucherHydrator implements VoucherHydratorInterface
{
    public function hydrate(TaxDocument $doc): BaseDocument
    {
        $invoice = new Invoice();
        $invoice->setUblVersion('2.1')
            ->setTipoComprobante($doc->getDocumentType())
            ->setSerie($doc->getSeries())
            ->setCorrelativo($doc->getNumber())
            ->setFechaEmision($doc->getIssuedAt())
            ->setCompany($this->buildCompany($doc->getBranch()->getCompany()))
            ->setClient($this->buildClient($doc->getCustomer()))
            // ... taxes, details, detractions ...
        ;
        return $invoice;
    }
}
```

### Invoice Calculator

`InvoiceCalculator` (with `brick/math`) computes exact amounts per UBL 2.1 rules:
- `taxable_amount` = sum of taxable line amounts (base for IGV)
- `igv_amount` = taxable_amount × IGV rate (currently 18%)
- `rc_amount` = service charge amount (for restaurant-style service charge)
- `subtotal` = taxable_amount + exonerated + unaffected
- `total` = subtotal + igv_amount + rc_amount - discount_amount

### Detraction Support

For transactions above S/700 in qualifying service categories, SUNAT requires a detraction:

```php
$invoice->setDetraccion([
    'code' => $doc->getDetraccionCode(),
    'percent' => $doc->getDetraccionPercent(),
    'amount' => $doc->getDetraccionAmount(),
    'medioPago' => $doc->getDetraccionPaymentMethod(),
])
```

### Safety Mechanisms

**Circuit breaker:** If a series has ≥ 3 consecutive SUNAT rejections, auto-sending is paused for that series. The operator must review and restart manually.

**Retry with back-off:** Transport errors (SUNAT service unavailable, network timeout) trigger Messenger retry with exponential back-off (configured in `messenger.yaml`).

**CDR consult recovery:** `GET /api/tax-documents/{id}/consult` calls `greenter/consulta-cpe` to fetch the CDR status for a document that was submitted but whose response was lost (e.g. server restart during SOAP call). This recovers documents stuck in `PENDING` status.

**Event log:** Every state transition (issued, sent, accepted, rejected, consulted, voided) is recorded in `tax_document_event` for audit trail.

### Void Documents (Comunicación de Baja)

```
POST /api/sales/{id}/void
  → Create Comunicación de Baja (RC document type)
  → Reference the original TaxDocument series and number
  → Dispatch to Messenger async transport
  → SUNAT processes next business day (batch processing)
```

### Daily Summary (Resumen Diario)

```
-- Scheduled or manual
→ Aggregate all BOLETA documents for the Lima calendar day
→ Create Resumen Diario (RA document type)
→ Submit to SUNAT
→ SUNAT processes asynchronously (returns ticket, not CDR immediately)
→ Consult ticket later to get CDR
```

---

## Consequences

### Positive

- **SUNAT compliance confidence** — `greenter/core` is battle-tested across thousands of Peruvian companies; its UBL 2.1 XML output is known to be accepted by SUNAT.
- **Maintained library** — SUNAT periodically updates XML schemas and web service URLs; `greenter/core` absorbs these changes with library updates.
- **All document types covered** — Facturas, boletas, notas de crédito, notas de débito, resúmenes diarios, and comunicaciones de baja are all supported.
- **Certificate management encapsulated** — Signing with the tenant's PFX/P12 certificate is handled by greenter; we only need to pass the certificate path/password.

### Negative

- **Vendor dependency for SUNAT contract** — If `greenter/core` stops being maintained or fails to track a SUNAT schema change, eFact's invoicing breaks until the library is updated or replaced.
- **Testing against SUNAT** — Full integration testing requires SUNAT's beta environment (`https://e-factura.sunat.gob.pe/ol-ti-itcpfegem/billService?wsdl` beta endpoint), which can be slow and unreliable.
- **Async failure visibility** — Since invoice sending is async via Messenger, a SUNAT rejection is not immediately visible to the cashier. The UI relies on Mercure push notifications to update the invoice status.

---

## Related ADRs

- ADR-011 — API Platform (SUNAT operations are custom State Processors on top of the TaxDocument resource)
- ADR-006 — Mercure (invoice status updates published to SSE after SUNAT response)
- ADR-013 — UTC timezone (invoice `issue_date` stored in UTC, converted to Lima for SUNAT XML submission)
- ADR-009 — Inventory ledger (stock deduction happens at sale time, not invoice time)
