# Treasury: technical PRD

Date: 2026-11-18
Status: Draft for review. Implements decision 1 and 4 of [ADR-021](../adr/ADR-021-finance-module-redesign.md)
(waves 1 and 2). One point refines decision 1 and needs the owner's confirmation: see D-A.

## 1. Goal and scope

Give every place money can sit (a cash drawer, a bank account, a wallet such as Yape) an **account**, and
record every movement of money into or out of those accounts in **one append-only ledger**, so that:

- a supplier payment made in cash shows up in the arqueo, and one made by transfer names the bank account it
  left (ADR-021 F2);
- bank reconciliation matches a statement line against one source instead of three (F1);
- the finance summary can show the real money position per account and currency (decision 3).

In scope: accounts, the movement ledger, payment-method-to-account mapping, a recording port used by Sales,
Accounts Payable and Payroll, transfers between accounts, position and movement queries, backfill and cutover,
integrity checks.

Out of scope (deliberately): changing how the general ledger posts (it keeps using `EconomicEvent` and the fixed
PCGE codes 101/1041; per-account GL codes are a later step), bank feeds, card settlement/fee modelling,
changing `CashSession` or the POS offline protocol, the finance screens (waves 3 to 4).

## 2. Starting point (verified in code on 2026-11-18)

| Fact | Where |
| --- | --- |
| Drawer ledger is `cash_movements` (plural), bound to a session (`cash_session_id NOT NULL`), carries `sale_id`, `payment_method_id`, `corrects_movement_id` and the POS offline fields (`idempotency_key`, `local_uuid`, `sync_status`, `device_id`) | live table |
| `OPENING_BALANCE` is re-injected as a movement when each session opens, so **summing all movements of a register double counts**. A drawer's balance is the open session's `expected` amount | ADR-004, `CashSession` |
| Non-cash `SalePayment` (card, Yape, Plin, transfer) creates no movement anywhere; reconciliation reads the payment row directly | `SalePaymentService`, `BankReconciliationMatchingService` |
| `SupplierPayment` writes no cash or bank movement, only an `EconomicEvent`; reversal mutates `reversed_at` | `SupplierPaymentService` |
| `bank_account` is company wide (no branch); `cash_register` is per branch | entities |
| `EconomicEvent` is routed `sync`, so GL posting happens in the same transaction as the payment | `messenger.yaml` |
| `bank_reconciliation_match` points at `supplier_payment`, `sale_payment` and `cash_movement` ids | entity |
| Architecture tests require every context to be declared (`ContextManifest`), every table to have an owner (`TableOwnershipTest`), no cross-context table writes (`CrossContextTableWriteTest`) and immutable ledger tables (`LedgerFiscalImmutabilityTest`) | `tests/Architecture` |

Lesson from the ADR-004 amendment: `CashMovement` (the class) and `cash_movements` (the table) were mixed up and
caused bugs that survived for seven weeks. Treasury uses names that cannot be confused with them.

## 3. Design

### 3.1 Bounded context

New context `Treasury` (`src/Treasury/`), declared in `ContextManifest`. It depends on nothing. `Sales`,
`AccountsPayable` and `Payroll` call it through one port; none of them writes its tables. Reads go through
Treasury query services, so `BankReconciliation` and the summary never join Treasury tables themselves.

### 3.2 Data model (tenant database, one Doctrine migration)

`treasury_account`
- `id` uuid, `kind` (`cash_register` | `bank` | `wallet` | `suspense`), `name`, `currency` char(3),
  `branch_id` nullable (null = company wide, e.g. a bank), `cash_register_id` nullable unique,
  `bank_account_id` nullable unique, `active`, `created_at`.
- Invariants: `kind=cash_register` requires `cash_register_id`; `kind=bank` requires `bank_account_id`; the
  currency of a `bank` account equals its `bank_account.currency`.

`treasury_movement` (append-only ledger, ADR-003)
- `id` uuid v7, `account_id`, `signed_amount` decimal(12,2) (never 0; in is positive, out is negative),
  `currency` (must equal the account's), `occurred_on` date, `occurred_at` datetime, `source_type`
  (`sale_payment` | `receivable_collection` | `customer_advance` | `supplier_payment` | `payroll_payment` |
  `transfer` | `opening_balance` | `statement_adjustment` | `reclassification`), `source_id`, `leg`
  (`main` | `in` | `out`), `party_type`/`party_id` nullable, `reference`, `description`, `transfer_id` nullable,
  `corrects_movement_id` nullable, `origin` (`live` | `backfill`), `created_by`, `created_at`.
- Idempotency: unique `(source_type, source_id, leg)`.
- Indexes: `(account_id, occurred_at)`, `(source_type, source_id, leg)` unique, `(corrects_movement_id)`.
- No UPDATE or DELETE ever. A correction is a new row with the opposite sign and `corrects_movement_id`.
  Protected by the repository interface (append only) and `LedgerFiscalImmutabilityTest`.

`treasury_payment_method_account`
- `(branch_id, payment_method_id)` unique, `account_id`. `Efectivo` has no row: its account is resolved at
  runtime from the user's open cash session.

`bank_reconciliation_match` gains a nullable `treasury_movement_id`; the three old columns stay until the old
paths are retired (section 5).

### 3.3 Drawers stay in `cash_movements` (D-A)

A register is a `treasury_account` of kind `cash_register`, but its movements are **not copied** into
`treasury_movement`. The port routes by account kind:

- `cash_register` account: appends to `cash_movements` through the existing writer, inside the open session
  (arqueo unchanged, POS offline protocol unchanged);
- every other kind: appends to `treasury_movement`.

Queries present one stream: `MovementsQuery` reads both stores and returns a common shape. A drawer's balance
is read through an adapter (the open session's expected amount, or the last closed session's counted amount),
never by summing.

Why: summing drawer rows is wrong (opening balance re-injection), the session and offline semantics live in
`cash_movements`, and a mirror would create two copies that can drift. Cost: two physical stores behind one
query service. Alternative (rejected): mirror every drawer movement into `treasury_movement` and treat
`cash_movements` as the session detail; simpler reads, but double writes and a permanent consistency check.

### 3.4 Port and services

```
interface RecordsMoneyMovement {
    public function record(MoneyMovementRequest $request): MoneyMovementRef; // idempotent
}
```

`MoneyMovementRequest`: source (type, id, leg), direction, amount (decimal string), currency, payment method,
branch, user, occurred at, optional party, reference, optional **account override**.

- `MoneyAccountResolver`: cash method gives the user's open session account (error if none, same as collections
  today); other methods use the override, else the `(branch, method)` mapping; if neither exists the movement
  goes to the branch's **suspense** account for that currency and the response carries a warning
  (`MoneyAccountNotConfigured`). A sale is never blocked by missing configuration, which matters for offline POS.
- `TransferMoney(from, to, amount, date, reference)`: writes the out leg and the in leg with one `transfer_id`.
  A drawer to bank transfer also appends the existing `DEPOSIT` cash movement.
- `ReclassifyMovement`: moves a suspense movement to a real account with two correcting rows.
- Queries: `PositionQuery` (balance per account and currency as of a date), `MovementsQuery` (grid protocol,
  filters by account, source, date, reconciled state), `UnreconciledMovementsQuery` (for bank reconciliation).
- Currency rule: a payment must be in its account's currency, otherwise the request is rejected (decision 3, no
  automatic conversion).

### 3.5 Integration points (all in the same transaction as the payment)

| Source | Change |
| --- | --- |
| `SalePaymentService`, POS payment path | non-cash: call the port; cash: unchanged (drawer). Key `sale_payment:{id}` so offline replays do not duplicate |
| Receivable collection and customer advance | same, with their own source types |
| `SupplierPaymentService` | call the port. Cash requires an open session and appends `EXPENSE` to the drawer; other methods write a bank or wallet movement |
| Supplier payment reversal | correcting movement, not a mutation |
| `Payroll` payment | call the port (cash or bank) |
| `EconomicEvent` | unchanged. Treasury does not post to the GL |

### 3.6 API, permissions, capability

- Internal API (not the public `/api/v1`): `GET /treasury/accounts`, `GET /treasury/positions`,
  `GET /treasury/movements` (x-grid-protocol), `POST /treasury/accounts`, `POST /treasury/transfers`,
  `PUT /treasury/payment-method-accounts`.
- Permissions: `treasury.view`, `treasury.manage` (accounts and mapping), `treasury.transfer`.
- Capability `treasury.accounts` (new `CapabilityKey`), off by default; `treasury.bank_reconciliation` keeps its
  own gate. With the capability off, recorders skip Treasury and behave as today.

## 4. Backfill and cutover

Strangler, as in ADR-004, with the lessons of its amendment: each step is reversible and has a check.

1. **Schema and accounts.** Migration. Command `treasury:accounts:seed` creates one account per
   `cash_register` and `bank_account`, plus a suspense account per branch and currency, and proposes the
   default mapping (decision 4): Transferencia and Depósito to the main bank account in PEN, Yape/Plin/cards
   to a wallet account the admin names. Nothing is recorded yet.
2. **Dual write** behind `treasury.accounts`. Recorders write movements; the old paths keep working.
3. **Backfill.** `treasury:backfill --from=<date> [--dry-run]`, idempotent (unique key), chunked, `origin=backfill`:
   non-cash `SalePayment`, bank and wallet `SupplierPayment`, receivable collections, advances and payroll
   payments. Historical supplier "BANK" payments go to the suspense account until the admin reclassifies them.
   Historical cash supplier payments are **not** backfilled: they never touched a drawer, and rewriting past
   arqueos would be wrong; the gap is documented in the activation notes.
4. **Opening balances.** Bank and wallet balances start at the activation date: the admin enters an
   `opening_balance` movement per account (the screen offers the last imported statement balance as a hint).
   Before that date the screens say "desde la activación".
5. **Read cutover.** Reconciliation reads `UnreconciledMovementsQuery`; the forecast reads `PositionQuery`.
   Old matches are re-pointed through a one-off mapping (`sale_payment_id` or `supplier_payment_id` to the
   movement with that source). Old query paths stay for two releases, then are removed.
6. **Rollback.** Turn the capability off. Tables stay; no destructive step exists before step 6 of the waves.

**Integrity check** `treasury:verify` (also run in CI against the seeded e2e data): every post-activation
non-cash payment has exactly one movement; no movement lacks a source; per source the sums match; per drawer
the adapter equals the session expected amount; suspense balance reported. Non-zero exit on a mismatch.

## 5. Testing

- Unit: resolver rules (cash, mapped, override, suspense, wrong currency), idempotency, sign and correction
  rules, drawer adapter balance.
- Integration: each source produces its movement; reversal produces the correction; an offline replay does not
  duplicate; the transfer writes both legs and the drawer `DEPOSIT`.
- Architecture: `ContextManifest` entry, table ownership, no cross-context writes, ledger immutability.
- E2E (extends the clean-stack suite): paying a supplier in cash lowers the drawer's expected amount; paying by
  transfer from account X lowers X; the movement appears in reconciliation.
- Static: Mago and PHPStan clean, verified with the CI image on a clean checkout (see the baseline lesson).

## 6. Work packets

**WP1: foundation.** Context, migration, entities, resolver, port, `TransferMoney`, `PositionQuery`,
`MovementsQuery`, accounts seed, config API and screen (accounts, mapping, opening balances), capability and
permissions. Acceptance: with the capability on, creating an account and a mapping works; `treasury:verify`
passes on an empty ledger; architecture tests pass.

**WP2: recorders.** Supplier payment and reversal, non-cash sale payment, receivable collection, advance,
payroll, with the account selector in the collect and pay dialogs. Backfill and verify command. Acceptance:
the four scenarios of section 5 pass on the e2e stack; offline replay test passes.

Later waves (not in this document): reconciliation on one source, the finance summary, the P&L.

## 7. Risks

| Risk | Mitigation |
| --- | --- |
| Drawer and ledger drift (D-A) | One writer per kind, `treasury:verify` in CI, no summing of drawer rows |
| Offline POS duplicates | Deterministic key from the payment id, unique index, replay test |
| Unmapped methods block sales | Suspense account plus warning; sale never fails on configuration |
| Admin never reclassifies suspense | Suspense balance on the config screen and in the summary |
| Currency mismatch | Rejected at the port; accounts carry one currency |
| GL still uses fixed 101/1041 | Explicitly out of scope; per-account GL codes are a follow-up |
| Confusing names with the cash ledger | `treasury_*` tables, `TreasuryMovement` class, never `CashMovement` |
| Ledger growth | Indexes above; capacity check against `docs/capacity-budgets.md` before WP2 |

## 8. Decisions to confirm

- **D-A (refines decision 1).** Drawers stay in `cash_movements` behind the port instead of being copied into
  the new ledger. Recommended. If you prefer the full mirror, WP1 grows and a permanent consistency check is
  needed.
- **D-B.** A cash supplier payment requires an open cash session, like cash collections do today. Recommended
  for arqueo integrity. Alternative: allow paying from a "petty cash" account that is not a drawer.
- **D-C.** Suspense account for unmapped methods instead of rejecting the payment. Recommended.
- **D-D.** Bank and wallet balances start at activation with an opening-balance movement entered by the admin.
  Recommended; history before that date is not reconstructed.

## 9. Not verified

I did not read the full `SalePaymentService`, the receivable and advance services, `Payroll` payment code or the
POS sync path line by line; section 3.5 lists where to hook, and WP2 must confirm each transaction boundary
(in particular that the payroll and advance flows run inside one transaction with their `EconomicEvent`).
Table volumes were not measured.
