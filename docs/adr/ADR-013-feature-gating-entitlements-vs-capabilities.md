# ADR-013: Feature Gating — Plan Entitlements vs. Vertical Capabilities

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented, inconsistently applied (see Consequences)

## Context

Reconstructed from `docs/PRD.md`'s "ADR-022: feature gates por plan" citation, never written up as a standalone document (see ADR-006 for the numbering-collision context shared by all reconstructed ADRs). `CONTEXT.md` already names the two concepts this ADR documents — Capability ("operational behavior... `inventory.multi_warehouse`, `cash.sessions`, `exports.excel`") and Entitlement ("what the commercial plan allows") — but never recorded how they're actually implemented or how they relate.

## Decision

Two separate, deliberately distinct gating mechanisms exist:

### Entitlements — what the commercial plan allows

`PlanFeature` (`src/ControlPlane/Billing/Entity/PlanFeature.php`) is a value object hydrated from the `plan_features` table via raw DBAL (not ORM). `TenantFeatureChecker` (`src/ControlPlane/Billing/Feature/TenantFeatureChecker.php`) implements `Nubit\Platform\Feature\Contract\FeatureCheckerInterface` with a **fail-closed** resolution order:

1. No tenant resolved → `false`.
2. A `tenant_feature_override` exists for this tenant/feature → use its value (lets support/ops grant or revoke a feature per tenant regardless of plan).
3. Otherwise, look up `plan_features` for the tenant's plan → use its value.
4. Feature not found anywhere → `false`.

Results are cached 300 seconds per tenant, with cache tags for invalidation. `FeatureVoter` (`src/ControlPlane/Billing/Feature/FeatureVoter.php`) exposes this to the backend as `IS_GRANTED('FEATURE_<KEY>')`. Frontend equivalents: `assets/shared/hooks/useFeature.ts`, `useEntitlements.ts`, and a `<FeatureGate>` component (`assets/shared/components/FeatureGate.tsx`).

### Capabilities — operational behavior per vertical

`VerticalPresetInterface` (`src/Settings/Vertical/`) governs behavior tied to a tenant's business type/vertical (retail, pharmacy, etc. — see `CONTEXT.md`'s VerticalPreset section), a separate axis from what the commercial plan permits.

**These are not the same mechanism and are not meant to be** — a capability describes *what kind of business this is*; an entitlement describes *what the customer paid for*. A tenant can be a pharmacy (capability) on the Free plan (entitlement) missing `exports.excel`.

## Consequences

**Positive**
- Fail-closed entitlement resolution means a bug or missing row denies access rather than silently granting it — the safer default for a billing-gated feature.
- Per-tenant overrides (`tenant_feature_override`) give support/ops a documented lever without needing a plan change or a code deploy.
- The Capability/Entitlement split avoids conflating "what this business needs to operate" with "what they're paying for" — a real distinction the code respects.

**Negative**
- **The mechanism is not applied universally.** `src/Inventory/Core/Service/BomExplosionService.php` (BOM/recipe explosion for composite products) has no `FEATURE_`/`hasFeature`/`IS_GRANTED` check anywhere — it runs for every tenant regardless of plan, unlike every other gated feature this ADR documents. This is the same gap flagged in earlier audit work (`PRD.md`'s Open Questions: "BOM/recipe explosion in production... GA or... behind a plan/vertical feature gate?") — this ADR confirms it's a real inconsistency in an otherwise-consistent system, not a documentation gap.
- Two gating vocabularies (`FEATURE_<KEY>` voter for entitlements, `VerticalPresetInterface`/capability checks for verticals) mean a developer adding a new gated feature must know which one applies — nothing in the code forces the right choice, and getting it backwards (gating a vertical-specific behavior by plan, or a paid feature by vertical) would compile and work while being semantically wrong.

## Alternatives Considered

**A single unified "feature flag" system for both plan and vertical gating** — not built; the two were kept separate. This is likely deliberate given how cleanly they're named in `CONTEXT.md` (Capability vs. Entitlement are described as orthogonal concepts there), but no record exists of a single-system alternative being explicitly considered and rejected.
