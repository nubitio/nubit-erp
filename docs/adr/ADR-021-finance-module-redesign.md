# ADR-021: Finance module redesign (proposal)

Date: 2026-11-18
Status: Accepted in direction, 2026-11-18 (decisions below). Implementation is underway:
the Treasury ledger and payment integrations, matching against Treasury movements, the finance summary,
reporting changes and drawer-to-bank/wallet transfers are in code. See the
[Treasury technical PRD](../architecture/treasury-technical-prd.md) for scope and limits.

## Context: what exists today

"Finanzas" is a menu group, not a module. Seven screens sit under it (plus Caja at the top level), each
built at a different time with a different pattern.

| Screen (route) | Frontend | Backend | Pattern |
| --- | --- | --- | --- |
| Cuentas por cobrar (`/accounts-receivable`) | `features/sales/` (grid, drawer, KPIs, dialogs) | `Sales` (`CustomerReceivable`, `CustomerAccountLedger`) | Composed, redesigned in #409 |
| Cuentas por pagar (`/accounts-payable`) | `features/accountsPayable/` (441 + 434 LOC) | `AccountsPayable` | `SchemaCrudPage` plus a summary and a forecast card |
| Conciliación bancaria (`/bank-reconciliation`) | `features/bankReconciliation/` | `BankReconciliation` (2.5k LOC) | Two-column custom screen |
| Reportes financieros (`/financial-reports`) | `features/financialReports/` | `Reporting` (4.5k LOC) | Custom, "operational estimate" |
| Contabilidad (`/accounting`) | `features/accounting/` | `Accounting` (1.4k LOC) | Custom tabs, native `<select>` |
| Gastos de personal, Activos fijos | `expenseReports`, `fixedAssets` | `ExpenseReports`, `FixedAssets` | `SchemaCrudPage` |
| Caja (`/cashbox`) | `features/cashRegister/` | `CashRegister` (ADR-004 strangler) | Mixed |

Money flows between them like this today:

```
SalePayment ──┐                         ┌─> CashMovement (only for cash collections, in an open session)
              ├─ EconomicEvent (sync) ──┤
SupplierPayment ┘                       └─> JournalEntry (GL, PCGE accounts 101/1041/121/421…)

BankReconciliation matches statement lines against THREE sources:
  SupplierPayment, SalePayment, CashMovement
```

## Decisions (grill-me session, 2026-11-18)

1. **Treasury, complete, in phases.** New context with `MoneyAccount` and append-only `MoneyMovement`; backfill
   from sales payments, supplier payments and cash movements; run both paths side by side before cutover
   (ADR-004 style). First deliverable: supplier payments write a movement and name an account.
2. **Resultados reads the general ledger when the accounting capability is on**; otherwise it falls back to
   the operational estimate, always labelled "Estimado". The two are never shown under the same name.
3. **Currency:** every balance stays in its original currency, no automatic conversion. Money accounts have a
   currency; open items keep the document's currency; lists and KPIs show one line per currency. A PEN
   equivalent appears only where the document stored its exchange rate, labelled as equivalent. (The repo
   already sells and buys in USD, so this is not optional.)
4. **Payment method to account:** each method has a default money account per branch (Efectivo = the user's
   open drawer; wallets and cards = their account; Transferencia and Depósito = the main bank account),
   configured once, overridable at payment time. A new method has no account until configured and the system
   warns.
5. **Main user: supervisor or owner** (PRD section 4 has no accountant persona). Resumen comes first;
   Contabilidad is a secondary view.
6. **Assumption, not asked:** Gastos de personal and Activos fijos stay in the Finanzas group under "Más".
   Cheap to move later.

## Findings

Each one is backed by code or by a screen, not by taste.

**F1. There is no single record of money moving.** A collection is a `SalePayment`, a supplier payment is a
`SupplierPayment`, and drawer cash is a `CashMovement`. Bank reconciliation has to query all three
(`BankReconciliationMatchingService`: "the three existing sources of truth"). Cash and bank are not
accounts with balances: the cash position is "open drawers only" (the forecast card says so itself) and a bank
balance exists only as imported statement lines.

**F2. Paying a supplier does not touch the cash drawer.** A cash collection from a customer writes a `CASH_IN`
into the collector's open session (`SalePaymentService`). `SupplierPaymentService` writes no `CashMovement`
and no bank movement: it only dispatches an `EconomicEvent` (kind `PAYABLE_PAYMENT_CASH` or `_BANK`) so the GL
credits 101 or 1041. A "BANK" payment names no `BankAccount`. Result: the arqueo does not see supplier cash
payments, and a transfer cannot be tied to the bank account it left.

**F3. Receivables and payables are mirror images built differently.**
Receivables have an append-only customer ledger (`CustomerAccountLedger`), but its own docblock says per-sale
debt "still lives on `SaleRecord.balanceDue`": two places hold the same fact. Payables mutate
`paid_total` / `balance_due` / `status` columns on `SupplierBill` and have no ledger. Aging is implemented three
times: SQL buckets in `CustomerReceivableProvider`, `PayablesAgingCalculator` (PHP) and `receivableAging.ts`
(frontend). Payments differ too: customers can pay "a cuenta" (`customer_payment_applications`), suppliers are
paid one bill at a time on a separate page (`/supplier-bill/:id/pay`).

**F4. Two profit-and-loss numbers can disagree.** `FinancialReportsPage` is "an operational estimate from
sales, purchases and cash"; `AccountingPage` is the double-entry ledger ("not a certified close"). Both exist,
both are called results, and nothing reconciles them.

**F5. The frontend has no shared vocabulary.** AR is composed, AP is the schema engine, the rest are bespoke.
`FinanceNavigation` shows three tabs (Por cobrar, Por pagar, Movimientos de caja y bancos) while the sidebar
already lists seven finance entries. ADR-019 already classes AP as a business screen (summary, forecast,
custom pay flow) that the engine fights.

**F6. Currency is assumed to be one.** The AR drawer and `openBalance` take the first invoice's currency;
`SupplierBill` has a `currency` column and the forecast sums amounts without converting. Either multi-currency
is out of scope (then say so) or these screens are wrong for USD customers.

## UI review (what I saw in the running app)

1. **The top bar title is wrong on 3 of 5 pages.** AR and AP show "Finanzas"; Bank reconciliation, Financial
   reports and Accounting show the branch name ("Principal"). I have not traced the cause (probably a fallback when the route is not matched in the menu tree). On top of
   that, the same double-header problem as Customers.
2. **AP error state breaks the layout.** The error panel has no height, so the retry button overlaps the
   pagination footer. (The error itself came from the dev server crashing, see Environment below, but the
   overlap is a real layout bug.)
3. **AP defaults to "Tarjetas" on desktop** (`defaultView: 'cards'`) for a list of invoices where columns and
   sorting matter.
4. **Explanation text instead of design.** AP carries two paragraphs about what "próximos vencimientos" includes;
   AR repeats the KPI totals in a sentence above the KPIs. Help belongs in a tooltip.
5. **The forecast card is half-width with empty space beside it**, and is the only place that tells you how
   much money you have, with the caveat "only open drawers, no banks".
6. **Inconsistent controls:** native `<select>` in Accounting, library selects elsewhere; filter rows in AR but
   a search box in AP; tabs styled three ways.
7. **Three different empty/loading treatments** (skeleton rows, grey blocks, text).
8. Tall controls and rows, as seen in Customers: a density token in `@nubitio/ui` is still pending.

## Questioned decisions

| Decision as it stands | Why I would change it |
| --- | --- |
| Finance organised by document type (AR, AP, bank) | People ask "how much do I have, who owes me, what do I pay this week, does my cash match". Documents are the means. |
| Cash, bank and wallets are not accounts | It makes F1 and F2 unfixable: there is nothing to attach a supplier payment to. |
| AP stays on `SchemaCrudPage` | The page needs a summary, a forecast and a custom pay flow around the grid. ADR-019 D1 says this is a composed screen. |
| Per-side aging and per-side payment flows | They drift. They should be one implementation parametrised by side. |
| GL posting via `EconomicEvent` per source | Fine, and synchronous (routed `sync`, same transaction). Keep it, but have it consume the new movement instead of each source. |

What I would **keep**: modular monolith boundaries (ADR-001), append-only ledgers (ADR-003), the sync
`EconomicEvent` to GL path, `Money` as decimal strings in PHP, and the AR drawer/dialog design from #409.

## Proposal

### P1. Information architecture

Replace the seven-item list plus tabs with a small set of jobs. Menu labels in Spanish:

- **Resumen** (new): money position (cash + banks + wallets), next 7/30 days (collections and payments),
  overdue, and the 3 actions that matter today.
- **Por cobrar** and **Por pagar**: the same screen with the side flipped (P3).
- **Caja y bancos**: accounts and balances, movements, transfers, reconciliation. Caja (drawers) stays in
  its own menu for cashiers; managers see its totals here.
- **Resultados**: one P&L (P2.4).
- **Contabilidad**: books for the accountant (ledger, trial balance, journal).
- **Más**: expenses, fixed assets.

Drop `FinanceNavigation`. The sidebar group plus a page-level switcher inside **Por cobrar / Por pagar** is
enough.

### P2. Backend

1. **Treasury context.** `MoneyAccount` (kind: `cash_drawer`, `bank`, `wallet`; currency; optional link to
   `BankAccount`) and an append-only `MoneyMovement` (account, signed amount, date, source type + id,
   reconciled-with). Sales, AccountsPayable, CashRegister and Payroll record movements through a port
   (`RecordsMoneyMovement`), per ADR-001. Cash drawers keep `CashSession`; a session's total is derived from
   its movements.
2. **Close F2 first.** Supplier payments pick a money account (default by payment method), write the movement,
   and the arqueo and the bank balance see it.
3. **Bank reconciliation matches one source** (`MoneyMovement`) instead of three. The matching service
   shrinks and the "unreconciled" query becomes one table.
4. **One P&L.** Financial reports read the GL when the accounting capability is on and fall back to the
   operational estimate otherwise, labelled as such. Never show both under the same name.
5. **Open items, one model.** A payable and a receivable are an *open item* with a party, due date, amount,
   settled amount and applications. Give payables an append-only account ledger like customers have
   (`supplier_account_entries`), make the ledger the source of truth and keep `balance_due` as a derived
   column updated in the same transaction. Aging buckets are computed once in the backend and returned by
   both endpoints; `receivableAging.ts` becomes a renderer.
6. **Payment applications for payables.** One payment can settle several bills (reuse the shape of
   `customer_payment_applications`). The per-bill pay page becomes a dialog.
7. **Currency:** decide (Q2) and enforce in one place.

Migration follows the ADR-004 strangler: backfill accounts and movements from existing sales payments,
supplier payments and cash movements, run both paths behind a capability, compare, then cut over.

### P3. Frontend

Three archetypes, all composed under ADR-019, built from `assets/shared/ui`:

- **OpenItemsPage(side)**: KPI aging strip, grid of parties with balance, drawer with documents, payments and
  actions, `SettleDialog` (collect or pay, multi-document), statement download. AR is the template; AP
  becomes the second consumer, which is the "second user" that justifies extracting it.
- **Hub**: the Resumen page, cards that link to filtered lists.
- **LedgerView**: movements, reconciliation, books and reports share toolbar, period selector and export.

Shared pieces to extract from what AR already has: `AgingStrip`, `PartyDrawer`, `SettleDialog`, `MoneyCell`,
`StatusBadge`, `PeriodSelector`. Rules for every screen: one title (the shell's), compact rows, grid fills the
height, tooltips for explanations, one error and one empty component.

### P4. Quick fixes that do not need the redesign

Top bar title fallback; AP error layout; AP default to table; drop the explanatory paragraphs; replace the
native selects in Accounting.

## Waves

Each wave ships and can stop there.

0. **Quick fixes (P4)** and the environment fix below. Days.
1. **Treasury foundation + supplier payments write movements (P2.1, P2.2).** Fixes the arqueo gap. Behind a
   capability, backfilled.
2. **Open items: shared aging, payable ledger, payment applications (P2.5, P2.6)**, then the AP screen on
   `OpenItemsPage`. Retires the schema-driven AP page and `/supplier-bill/:id/pay`.
3. **Caja y bancos + one-source reconciliation (P2.3)**, and the **Resumen** hub.
4. **One P&L and Resultados (P2.4)**, Accounting on `LedgerView`.

Not doing: bank feed integrations, multi-company consolidation, replacing the GL, or touching Sales
document flows.

## Open questions (original list, now resolved above; Q5 is the assumption in decision 6)

1. **Q1.** Do you accept a Treasury context with accounts and movements (the big change), or only the
   smaller path: supplier payments write a `CashMovement` and a `bank_account_id`, no new context?
2. **Q2.** Is USD (or any non-PEN) a real requirement for customers or suppliers? It decides F6.
3. **Q3.** Who is the main user of Finanzas: the owner/administrator (Resumen first) or an accountant
   (books first)? It orders the waves.
4. **Q4.** Is the operational P&L worth keeping when accounting is off, or should Resultados require the GL?
5. **Q5.** Are employee expenses ("Gastos de personal") and fixed assets finance, or HR/assets with their own
   menu?

## Environment finding (not a design issue)

The dev `web` container crashes with `zend_mm_heap corrupted` (the container shows 8 restarts), which is what
produced the "No se pudo cargar la información" error on the AP screen. The `Dockerfile` pins PHP 8.5.12,
but the local image was built on 2026-09-17 with PHP 8.5.0 ZTS. Rebuilding (`docker compose build web`)
should remove it; I have not done that.

## What this document did not verify

Screens were reviewed in a headless browser on the local demo tenant with almost no finance data, so empty
states are over-represented. Bank reconciliation was seen only in its loading state. I did not read
`Reporting` or `Payroll` in depth, so F4 rests on the two screens' own captions. Effort for the waves is not
estimated yet.
