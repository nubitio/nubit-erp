# ADR-008: Custom Stateless JWT Authentication (not LexikJWTAuthenticationBundle)

Date recorded: 2026-08-19 (retroactive — documents a decision already live in code; see Context)
Status: Accepted — Implemented

## Context

Reconstructed from `docs/PRD.md`'s "ADR-003: JWT custom" citation, never written up as a standalone document (that number collides with the unrelated real `ADR-003`, append-only ledgers — see ADR-006 for the numbering-collision context shared by all reconstructed ADRs).

eFact's tenant app authenticates via JWT rather than Symfony sessions. Every request resolves both a tenant (see ADR-007) and a user within that tenant's database — a combination the standard session-based `switch_user` mechanism doesn't fit, since there's no shared session store meaningfully scoped across a per-request database swap.

## Decision

Use a custom authenticator (`Nubit\AdminBundle\Auth\JWTAuthenticator`, wired as `custom_authenticator` in `config/packages/security.yaml`) instead of `LexikJWTAuthenticationBundle` or Symfony's built-in session-based auth, for the tenant-facing firewall. Authentication is **stateless, JWT-per-request**: every request carries its own token: there is no server-side session to look up.

Tenant impersonation (an operator acting as a tenant user from the ControlPlane) does not use Symfony's `switch_user` for the same reason — it's incompatible with a stateless, JWT-per-request, per-tenant-DB-connection firewall. Instead, `App\ControlPlane\Operator\Api\ImpersonationApiController` is a dedicated endpoint that mints a scoped JWT for the target tenant user directly.

## Consequences

**Positive**
- No shared session store needed across the per-request tenant database swap (ADR-007) — the token carries everything the request needs.
- Impersonation is an explicit, auditable action (a controller endpoint that mints a token) rather than a session-mutation side effect.
- Stateless auth scales horizontally without sticky sessions or a shared session backend.

**Negative**
- Custom authenticator code is bespoke — it doesn't get LexikJWT's community maintenance, refresh-token conventions, or documentation for free.
- Token revocation before expiry requires deliberate handling (blocklist, short expiry + refresh) rather than the trivial "delete the session row" a session-based scheme gets for free.
- Anyone debugging auth needs to understand this project's specific authenticator rather than a widely-documented bundle.

## Alternatives Considered

**LexikJWTAuthenticationBundle** — not adopted; the team built a custom authenticator instead, likely to control the interaction between token issuance and tenant/impersonation resolution more directly than a general-purpose bundle allows. (This ADR does not have access to the original discussion; if LexikJWT was formally evaluated and rejected, that reasoning is not recorded anywhere in the repo — worth capturing from whoever made the original call, if still available.)

**Session-based auth with `switch_user`** — rejected. Doesn't compose with stateless JWT-per-request and per-tenant-connection resolution; `switch_user` assumes a persistent session tied to one identity provider, not a request that resolves a different tenant database each time.
