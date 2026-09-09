# ADR-011: SUNAT Billing — Greenter as Data Model Only, Lycet/Bill as External Submission Service

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented

## Context

Reconstructed from `docs/PRD.md`'s "ADR-012: facturación Greenter/SUNAT" citation, never written up as a standalone document (number space collides with nothing existing in `docs/adr/`, but is part of the same reconstructed set — see ADR-006).

[Greenter](https://greenter.dev/) is a well-known PHP library for generating UBL 2.1 XML, digitally signing, and submitting Peruvian tax documents to SUNAT via SOAP. eFact depends on it (`greenter/core` ^5.2, `greenter/consulta-cpe` ^1.1), which could be read as "eFact submits to SUNAT via Greenter directly." That is not what the code does, and the gap between the library's full capability and how eFact actually uses it is worth recording explicitly so nobody "fixes" this by wiring up Greenter's SOAP client believing it's a missing integration.

## Decision

**Greenter is used only for its typed data model**, not its XML generation, signing, or SOAP submission capabilities. `Greenter\Model\Sale\Invoice`, `Greenter\Model\Response\StatusCdrResult`, `Greenter\Model\Sale\FormaPagos\FormaPagoCredito`, and similar classes are used as strongly-typed DTOs to represent a tax document, referenced across 15+ files in `src/Billing/Sunat/` and `src/Billing/Despatch/`.

**Actual submission to SUNAT goes through an external HTTP service**, referred to interchangeably in the code and docs as "Bill" and "Lycet" (same service, two names in circulation — this ADR does not resolve which name should win going forward, just records that they're the same thing). `App\Billing\Sunat\Infrastructure\BillRepository::sendBill()` serializes the Greenter `Invoice` object to JSON and `POST`s it to a URL built by `LycetUrlBuilder`, targeting `BILL_API_BASE_URL` (`.env`, `https://api.efact.app/api/v1/`) authenticated with `BILL_API_TOKEN`. That external service — not this codebase — generates the actual UBL XML, signs it, and submits it to SUNAT over SOAP.

For e2e tests, `App\Setup\Infrastructure\Billing\E2eStubBillRepository` implements the same repository interface as a stub, consistent with `README.md`'s description of the golden-path fiscal test as using a SUNAT "stub."

## Consequences

**Positive**
- eFact never needs to manage SUNAT digital certificates' XML-signing details, SOAP envelope quirks, or SUNAT endpoint changes directly — that complexity is isolated behind one external HTTP boundary (`BillRepository`) and one interface, swappable for the e2e stub in tests.
- Greenter's typed model still gives compile-time-ish structure to tax document data before it crosses that boundary, rather than hand-building request payloads from arrays.

**Negative**
- eFact has an operational dependency on `api.efact.app` (or whatever `BILL_API_BASE_URL` points to) being available and correct — a SUNAT submission failure could be either a genuine SUNAT rejection or an outage/bug in that external service, and the two need different responses.
- The "Greenter/SUNAT" framing in `docs/PRD.md` is misleading on its own — it reads as "we integrate with SUNAT via Greenter," when the actual integration point is a private HTTP API. Anyone debugging a submission failure should look at `BillRepository`/`LycetUrlBuilder` and the external service, not at Greenter's own SOAP client (which this codebase never calls).
- "Bill" and "Lycet" being used interchangeably for the same service, with no canonical name recorded anywhere, is a minor but real source of confusion when searching the codebase or logs.

## Amendment 2026-09-08: Tenant-Owned Printable Representation

PDFs are not fiscal source data. eFact renders the printable representation for invoices, receipts and notes locally from its `Voucher` read model using `VoucherPdfRenderer` and the existing Twig A4 template. This makes the tenant's logo, bank account/CCI, QR and layout an eFact concern rather than configuration duplicated in the submission provider.

Lycet remains the transitional provider for SUNAT submission and status/artefact retrieval. Its legacy PDF endpoints remain implemented behind repository interfaces for compatibility, but no tenant-facing download or invoice-email flow calls them.

## Alternatives Considered

**Greenter's built-in SOAP client, signing, and XML generation, used directly against SUNAT** — not adopted. The external Lycet/Bill service does this instead, likely to centralize certificate/signing logic across multiple products (the PRD mentions three: `efact`, `efact-app`, `efact-site`) rather than duplicate SUNAT SOAP handling in each. Not documented anywhere as an explicit comparison — inferred from the architecture actually built.
