# eFact Next-Level Product Plan

**Date:** 2026-05-14
**Perspective:** Product owner / investor after acquisition
**Planning horizon:** 90 days
**Primary objective:** turn the existing technical platform into a reliable, sellable and supportable vertical SaaS product.

---

## Executive Thesis

eFact is already more than an MVP. The acquired asset includes a multi-tenant SaaS backend, Web POS, Android POS, SUNAT electronic invoicing, inventory, cash register flows, ControlPlane, feature gates, backups, AI/MCP tooling and operational scheduling.

The next level is not adding more disconnected features. The next level is product focus:

- Make the first invoice path reliable and measurable.
- Package the existing capabilities into plans customers understand.
- Pick one beachhead vertical.
- Make SUNAT reliability the brand promise.
- Turn ControlPlane into the operator cockpit.
- Reduce support burden through guided onboarding and instrumentation.

North-star promise:

> Vende rapido. eFact se encarga de SUNAT.

---

## Strategic Positioning

### Target Market

Initial market: Peruvian SMBs that need point-of-sale, inventory and SUNAT-compliant electronic receipts in one workflow.

Recommended beachhead:

- Minimarkets.
- Pharmacies.
- Small retail shops with barcode/SKU workflows.

Secondary market after the core flow is proven:

- Simple restaurants.
- Local distributors with multi-warehouse operations.
- Multi-branch stores.

### Why Not Sell To Everyone Yet

The current product has broad capabilities, but broad positioning increases support load. The first commercial motion should focus on one vertical where the existing product strengths already matter:

- SUNAT compliance.
- Fast checkout.
- Barcode product lookup.
- Inventory accuracy.
- Offline tolerance.
- Receipt printing.
- Expiry/lot tracking as a premium differentiator for pharmacies.

---

## Product Packaging

Map existing feature gates and capabilities into clear commercial plans.

| Plan | Target Customer | Included Outcome |
|---|---|---|
| Starter | Single-location shop | POS, boleta/factura, basic inventory, 1 branch, 1 caja, standard reports |
| Pro | Growing shop | Multi-user, advanced reports, Excel exports, stock alerts, customer lists, branding |
| Business | Inventory-heavy or multi-branch shop | Multi-warehouse, lot tracking, backups, bulk PDF/XML, higher limits |
| Enterprise | Operators/franchises/integrators | API access, webhooks, custom domain, priority support, custom onboarding |

Immediate product work:

- Convert `PlanFeatureManager::FEATURE_KEYS` into a public pricing/entitlement matrix.
- Add tenant-facing upgrade prompts when a feature returns HTTP 402.
- Decide which advanced inventory features are GA versus gated: lot tracking, BOM/composite products, multi-warehouse and bulk exports.
- Remove any mismatch between marketing copy, registration plan selection and ControlPlane plan definitions.

---

## Revenue-Critical Journey

The core journey is the only path that matters until activation is proven:

1. Register tenant.
2. Verify email.
3. Configure company/RUC.
4. Configure SUNAT certificate/credentials.
5. Create branch, warehouse, caja and voucher series.
6. Create/import first product.
7. Open caja.
8. Create sale.
9. Generate boleta/factura.
10. Send/consult SUNAT.
11. Print/share receipt.
12. See first report.

Success standard:

- p95 time to first valid invoice <= 10 minutes.
- At least 80% of activated tenants complete first invoice without support.
- First invoice failure reasons are tracked and reviewed weekly.

---

## 90-Day Roadmap

### Days 1-30: Stabilize The Money Path

Product outcomes:

- Define the beachhead vertical and its default setup.
- Publish pricing/plan draft for Starter, Pro, Business and Enterprise.
- Define first-invoice funnel events and dashboard.
- Classify advanced features as GA, beta or internal-only.

Engineering outcomes:

- Golden E2E path for Web POS: login, branch select, product, caja, sale, invoice, receipt.
- Golden API path for SUNAT with mocked external service responses.
- Verify tenant provisioning flow from registration to first tenant login.
- Add operational checks for Scheduler, SUNAT pending documents and failed provisioning.
- Remove, hide or clearly label mock/legacy auth surfaces that can confuse developers.

Business outcomes:

- Interview 10 SMB owners in the target vertical.
- Manually onboard 3 pilot businesses.
- Track every support interaction by root cause.
- Capture onboarding objections and pricing reactions.

Exit criteria:

- One vertical chosen.
- One pilot completes daily sales in production-like conditions.
- First-invoice funnel is observable.
- Critical onboarding blockers are listed with owners.

### Days 31-60: Productize Activation

Product outcomes:

- Guided onboarding checklist in the tenant UI.
- SUNAT health/status panel for tenant admins.
- Clear upgrade UX for feature-gated actions.
- Receipt/printing flow polished for target hardware.

Engineering outcomes:

- Provisioning retries and failure states visible in ControlPlane.
- Scheduler jobs monitored through logs, metrics or failure reports.
- SUNAT rejection messages translated into actionable tenant/operator language.
- Backup and certificate status visible to operators.
- Android POS stabilized only for the selected pilot workflow, not every possible flow.

Business outcomes:

- Launch paid pilot pricing.
- Build a sales script around SUNAT reliability and fast checkout.
- Explore channel partners: accountants, POS hardware sellers and local tech support providers.
- Produce first customer story or internal case study.

Exit criteria:

- 5 paying or paid-pilot tenants.
- Support tickets per activated tenant trending down.
- SUNAT issue handling has a written runbook.
- Upgrade prompts are wired to plan features.

### Days 61-90: Prepare Repeatable Growth

Product outcomes:

- Public beta for the beachhead vertical.
- In-product help for the first-invoice path.
- Tenant admin dashboard with sales, SUNAT pending/rejected and stock alerts.
- Basic migration/import path for products and customers.

Engineering outcomes:

- CI protects revenue-critical flows.
- Production SLOs defined for invoicing, provisioning, backup and sync.
- Backup/restore drill executed for at least one tenant copy.
- Core docs and runbooks updated after pilot learnings.

Business outcomes:

- Target 20 paying tenants.
- Track activation, retention, support load and ARPA by plan.
- Decide whether to double down on the first vertical or split into a second vertical.

Exit criteria:

- 20 paying tenants or a clear reason why not.
- Activation path is repeatable without engineering intervention.
- Product packaging maps to actual willingness to pay.
- Support burden is low enough to scale sales.

---

## ControlPlane As Operator Cockpit

ControlPlane should become the operational center of the SaaS business.

Required operator views:

- Active tenants by plan/status.
- Failed or stuck provisioning flows.
- Tenants with SUNAT rejection streaks.
- Pending invoices older than 24 hours.
- Certificate sync failures and expiring certificates.
- Backup failures and last successful backup per tenant.
- Payment webhook failures and duplicate events.
- Feature overrides expiring soon.
- Usage by plan and quota pressure.

Operational principle:

- Any tenant-facing failure that can cause churn must be visible in ControlPlane before the customer reports it.

---

## SUNAT Reliability As Differentiator

SUNAT should be the emotional center of the product promise. Customers fear penalties and rejected documents more than they value advanced dashboards.

Product requirements:

- Tenant-visible SUNAT status for each document.
- Human-readable rejection causes.
- Safe resend/consult actions.
- Operator alerts for rejection streaks.
- Daily summary of accepted, rejected and pending documents.
- Clear distinction between local sale success and SUNAT acceptance.

Success metrics:

- `sunat_acceptance_rate_24h >= 99.5%`.
- `pending_tax_documents_older_than_24h = 0` target, reviewed daily.
- `rejection_streak_incidents` tracked with root cause.

---

## QA And Reliability Priorities

Do not chase broad coverage first. Protect the flows that produce revenue, tax compliance and customer trust.

Required regression suite:

- Login and branch selection.
- Product creation/import.
- Caja open and close.
- Cash sale.
- Sale with DNI customer.
- Sale with RUC customer.
- Boleta generation.
- Factura generation.
- SUNAT send/consult with mocked accepted/rejected responses.
- Receipt print/share/download.
- Offline sale creation and sync.
- Tenant provisioning.
- Feature gate blocked/unblocked behavior.

Quality gates:

- Any change touching POS must preserve the golden sale flow.
- Any change touching Billing/SUNAT must preserve retry, circuit breaker and CDR consult recovery.
- Any change touching ControlPlane must preserve tenant isolation and operator auditability.

---

## Metrics

### Activation

- Time to first invoice.
- Percentage of tenants completing onboarding.
- Percentage of tenants making 5+ sales in first week.
- Percentage of tenants importing or creating 20+ products.

### Reliability

- SUNAT acceptance rate within 24 hours.
- Pending documents older than 24 hours.
- Offline sync success rate.
- Provisioning success rate.
- Backup success rate.
- Scheduler job failure count.

### Revenue

- Trial-to-paid conversion.
- Monthly recurring revenue.
- Average revenue per account.
- Churn.
- Expansion by feature-gated upgrades.

### Support

- Tickets per activated tenant.
- Top support root causes.
- Median time to resolution.
- Percentage of issues detected before customer report.

---

## Investment Priorities

### Fund Now

- Onboarding and first-invoice funnel.
- SUNAT reliability dashboard and runbooks.
- ControlPlane operational views.
- E2E protection for money path.
- Pricing/plan UX.
- Customer migration/import basics.

### Delay

- Broad AI assistant marketing.
- Marketplace/e-commerce integrations.
- Complex restaurant-only features.
- Multi-country tax support.
- Full ERP accounting.
- Advanced franchise analytics until cross-tenant strategy is explicitly approved.

### Kill Or Hide Until Proven

- Any mock/demo auth path visible to contributors or customers.
- Features that cannot be explained in one customer outcome.
- Experimental flows that create support tickets without clear revenue impact.

---

## Key Risks

- The platform remains too broad and hard to explain.
- SUNAT failures damage trust before the brand is established.
- Android and Web POS diverge in behavior.
- Onboarding requires human support and blocks self-serve growth.
- Pricing tiers do not match real willingness to pay.
- ControlPlane lacks proactive visibility, creating reactive support load.
- Advanced inventory features are sold before operational maturity.

---

## Decision Gates

### Day 30 Gate

Continue if:

- First-invoice path is observable.
- At least one pilot uses the product for real daily sales.
- Top onboarding blockers are known and solvable.

Stop or narrow scope if:

- SUNAT setup cannot be made repeatable.
- The chosen vertical has weak willingness to pay.
- Support burden is dominated by product ambiguity rather than bugs.

### Day 60 Gate

Continue if:

- 5 paying or paid-pilot tenants exist.
- Core support issues are declining.
- Operator workflows catch problems before customers do.

Reposition if:

- The target vertical does not activate.
- Another vertical shows stronger organic pull.

### Day 90 Gate

Scale if:

- 20 paying tenants or a credible repeatable path to 20 exists.
- Activation works without engineering intervention.
- The pricing/plan model is validated.
- SUNAT reliability is a customer trust asset, not a liability.

Do not scale sales if:

- Every onboarding still requires custom engineering.
- Invoice reliability cannot be monitored or explained.
- Product scope remains too broad for the support team.
