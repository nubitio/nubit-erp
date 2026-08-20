# ADR-016: `SLICE-*` and `SPEC-*` Traceability IDs Reference Planning Documents Not in This Repo

Date recorded: 2026-08-19
Status: Accepted (documentation-practice finding, not a technical decision)

## Context

While reconstructing ADR-007 through ADR-015 (see ADR-006 for why those exist — `docs/PRD.md` cited ADR numbers that were never written up), a third and fourth identifier scheme turned up, distinct from both `docs/adr/ADR-*` and the phantom `ADR-*` citations in `docs/PRD.md`:

- **`SLICE-*`** — e.g. `SLICE-002`, `SLICE-004`, `SLICE-005`, `SLICE-006`, `SLICE-008`, `SLICE-014`, `SLICE-015`, `SLICE-016`, `SLICE-CP-01/02/03`, `SLICE-SEC-001`, `SLICE-TEST-001` — found in `CHANGELOG.md`, migration `getDescription()` strings, test class docblocks, `git log` commit subjects, `.github/workflows/ci-main.yml` comments, and `docker/frankenphp/Caddyfile` (the CSP-omission comment that started this investigation: "Security headers — SLICE-005 SEC-3").
- **`SPEC-*`** — e.g. `SPEC-003`, `SPEC-004`, `SPEC-005`, `SPEC-007` — found in `docs/architecture/platform-boundary-map.md` (titled "Mapa de frontera Platform (SPEC-004)"), `docs/architecture/nubit-backend-extraction-plan.md`, a migration description, and several test docblocks.

Both schemes consistently reference numbered acceptance criteria (`AC-1`, `AC-2`, ..., and in the CSP case a sub-item `SEC-3`) — e.g. `tests/Identity/Security/AccessControlTest.php`: *"Regression tests for SLICE-005 — security hardening... AC-1/AC-2... AC-5..."*, and `tests/ControlPlane/.../TenantFeatureCheckerTest.php`: *"Covers SPEC-005 ACs..."*. This is the signature of a real planning methodology (feature specs and delivery slices, each with numbered acceptance criteria) — not arbitrary tags. **No document defining any individual `SLICE-*` or `SPEC-*` ID exists anywhere in this repository.** They are cited as if a reader can look them up, but there is nothing to look up here.

`SLICE-*` and `SPEC-*` are also independently numbered from each other, not aliases — e.g. `SLICE-005` (security hardening / access control) and `SPEC-005` (entitlements / `TenantFeatureChecker`) are two unrelated pieces of work that happen to share the number "005" under different prefixes.

## Decision

Record what these are, so a future reader doesn't mistake "no document found" for "this wasn't planned" or waste time searching this repo for a `SLICE-005` file that doesn't exist:

- `SPEC-*` and `SLICE-*` are traceability IDs into a planning process (specs and delivery slices, each with acceptance criteria) that ran **outside this repository** — an external tool, wiki, or local files that were never committed and are presumably gone now. This repo only has the *residue*: code comments, test docblocks, migration descriptions, and `CHANGELOG.md` entries that cite the ID without the ID's content.
- This is a **different kind of artifact than an ADR**. An ADR records an architectural decision and its reasoning for the codebase's own history. A `SPEC-*`/`SLICE-*` reference records *which unit of planned delivery work* produced a change — closer to a ticket/issue reference (`JIRA-123`, `#456`) than to an architecture decision. Reconstructing 20+ of them as ADRs the way ADR-007–015 did for the `docs/PRD.md` citations is not the right fix — most `SLICE-*`/`SPEC-*` work is implementation detail (a timezone fix, a dependency upgrade, a dashboard migration), not an architectural decision worth an ADR.
- The one concrete case this investigation traced fully — `docker/frankenphp/Caddyfile`'s "CSP intentionally omitted, SLICE-005 SEC-3" — remains **unresolved**: `SLICE-005`'s own test suite (`AccessControlTest`) confirms it covered access-control hardening (AC-1, AC-2, AC-5 verified: authenticated endpoints, `JWTManager` rejecting empty `APP_SECRET`), which is consistent with a security-hardening slice reasonably including a CSP decision — but *why* CSP specifically was scoped out (deferred to a later slice? deliberately rejected as high-friction for an admin panel with inline scripts? simply not gotten to?) cannot be recovered from this codebase. If the omission needs defending later (a pentest, a customer security review), that answer has to come from whoever ran `SLICE-005`, not from this repo.

## Consequences

**Positive**
- Future readers (human or agent) hitting a `SLICE-*`/`SPEC-*` reference now know to stop searching this repo for it and, if the "why" actually matters, go ask a human — rather than assuming it's findable with more grep.
- Confirms the CashRegister migration (ADR-004) had real planning behind it (`SLICE-008`/`SPEC-007`) that this repo's `docs/adr/ADR-004` never referenced — which is *why* the first audit pass on ADR-004 (see its 2026-08-19 amendment) underestimated how much of the functional cutover had actually shipped. Cross-referencing `git log` for `SLICE-*`/`SPEC-*`, not just reading the ADR, is now a known-useful step before declaring a migration abandoned.

**Negative**
- Real design rationale (like the CSP decision) is unrecoverable from the repository alone. Any future security review of `/admin` will hit this gap directly.
- Three-and-a-half traceability schemes now confirmed in this codebase (`docs/adr/ADR-*`, `docs/PRD.md`'s phantom `ADR-*` citations now resolved to ADR-007–015, `SPEC-*`, `SLICE-*`) with no cross-reference between them anywhere. A change touching, say, entitlements might be described in a commit as `SPEC-005`, in this ADR set as `ADR-013`, and in neither place pointing to the other.

## Alternatives Considered

**Reconstruct every `SLICE-*`/`SPEC-*` ID as its own ADR, the way ADR-007–015 did for `docs/PRD.md`'s citations** — rejected for this pass. Those had a specific trigger (`docs/PRD.md` citing them as "Decisiones vigentes," i.e., presenting them as documented architecture); most `SLICE-*`/`SPEC-*` references are inline traceability on implementation-level changes, not claims of a documented decision. Worth doing individually, later, only for ones that turn out to hide a real undocumented architectural choice the way `SLICE-008` did for CashRegister — not as a blanket sweep.
