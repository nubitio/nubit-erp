# ADR-017: SmartCrud Canonical Field Contract

**Status**: Accepted
**Date**: 2026-03-23
**Deciders**: adr-writer, spex-frontend
**Related Specs**: SPEC-004
**Supersedes**: 

## Context

SmartCrud is evolving from an internal/dev-testing capability into a production-ready frontend abstraction. The current field authoring model mixes Hydra-derived metadata, ad hoc field overrides, manual factories, and incremental patches. That overlap makes field behavior harder to reason about, creates ambiguity about which layer is authoritative, and increases the risk of invalid or conflicting configurations.

For SPEC-004 and TASK-227, the project needs a single canonical contract for authoring and resolving SmartCrud fields that is explicit enough for production use. Backward compatibility is not a decision driver because SmartCrud currently exists only in dev/testing and has not yet become a stable public contract. The chosen contract must preserve backend-described fields in Hydra-backed mode, while also supporting frontend-only synthetic fields such as `plainPassword`, typed declarative augmentation, and first-class create/edit operation-aware behavior.

The solution must produce deterministic field resolution, reject ambiguous merges, and surface developer-facing errors for invalid configuration rather than silently accepting inconsistent state.

## Decision Drivers

- Must define one production-ready canonical API instead of overlapping legacy mechanisms
- Must keep Hydra/OpenAPI as the baseline source of truth in Hydra-backed mode
- Must support synthetic fields such as `plainPassword`
- Must support declarative typed augmentation of backend-described fields
- Must support first-class create/edit operation-aware behavior
- Must enforce deterministic resolution order and explicit conflict validation
- Backward compatibility is not required because SmartCrud is not yet a production contract

## Considered Options

1. **Canonical structured contract with Hydra-backed augmentation pipeline** — define a single field contract where Hydra/OpenAPI provides the baseline and frontend config augments, injects, or constrains fields through a typed resolution pipeline
2. **Retain current mixed model with fieldOverrides/manual factories plus incremental patches** — continue supporting multiple overlapping authoring mechanisms and evolve them incrementally
3. **Fully manual field authoring without Hydra baseline** — require explicit frontend declaration for all fields and ignore Hydra/OpenAPI as a baseline contract

## Decision Outcome

**Chosen option**: **Canonical structured contract with Hydra-backed augmentation pipeline**

**Rationale**: This option creates a single authoritative authoring model while preserving the strongest existing source of truth: backend-described Hydra/OpenAPI metadata. It supports synthetic fields and operation-aware behavior without giving up consistency, because all augmentations pass through one typed resolution pipeline with deterministic ordering and explicit validation. Option B preserves ambiguity and legacy overlap, which is exactly what the ADR is intended to remove. Option C simplifies resolution mechanics but duplicates backend knowledge in the frontend, loses automatic alignment with backend-described fields, and makes Hydra-backed SmartCrud materially less valuable.

## Pros and Cons of the Options

### Option A: Canonical structured contract with Hydra-backed augmentation pipeline

**Pros**:
- Establishes one canonical production-facing API for SmartCrud field authoring
- Keeps Hydra/OpenAPI as the default source of truth for backend-described fields
- Supports synthetic fields, typed augmentation, and operation-aware create/edit behavior in one model
- Enables deterministic resolution order, conflict validation, and clear developer-facing errors

**Cons**:
- Requires designing and enforcing a stricter schema and validation layer
- Increases upfront implementation effort versus preserving the current ad hoc model

---

### Option B: Retain current mixed model with fieldOverrides/manual factories plus incremental patches

**Pros**:
- Lowest short-term migration cost
- Preserves maximum flexibility for local edge cases

**Cons**:
- Leaves multiple overlapping mechanisms in place with unclear precedence
- Makes deterministic resolution and conflict validation harder to guarantee
- Continues accumulating design debt in a contract intended for productionization

---

### Option C: Fully manual field authoring without Hydra baseline

**Pros**:
- Gives frontend authors full control over every field definition
- Avoids complexity from merging Hydra metadata with frontend augmentations

**Cons**:
- Duplicates backend-described field knowledge in frontend code
- Discards Hydra/OpenAPI as the natural baseline in Hydra-backed mode
- Makes schema drift and maintenance overhead more likely

## Consequences

### Positive
- SmartCrud gains a single canonical field contract suitable for production hardening
- Backend-described fields remain aligned with Hydra/OpenAPI in Hydra-backed mode
- Synthetic and operation-specific fields can be expressed without special-case escape hatches
- Invalid configuration becomes easier to detect early through explicit validation

### Negative
- Existing experimental field authoring patterns will need to be replaced instead of preserved
- Teams lose some permissive flexibility from legacy overlapping mechanisms

### Risks
- Resolution rules may still be misunderstood by developers — Mitigation: document the pipeline clearly and make validation errors actionable
- Hydra metadata may be incomplete for some resources — Mitigation: allow explicit typed augmentation and synthetic field declaration on top of the Hydra baseline

## Implementation Notes

The canonical contract should distinguish at least: baseline Hydra-derived fields, declarative typed augmentation of those fields, synthetic field insertion, and operation-aware behavior for create/edit contexts. Resolution order should be deterministic and documented. Conflicts such as duplicate field identifiers, incompatible type overrides, invalid operation scoping, or unsupported augmentation targets should fail fast with developer-facing errors rather than silently degrading.

Because backward compatibility is not required, legacy `fieldOverrides`/manual-factory patterns should not define the long-term API surface. Migration can favor clarity over compatibility shims.

## References

- ADR-014 — Prior SmartCrud Hydra metadata direction that established Hydra/OpenAPI as a key input to SmartCrud design
