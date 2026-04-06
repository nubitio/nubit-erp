# ADR-003 — Custom JWT Authentication over LexikJWT

## Status

Accepted

## Date

2026-04-02

---

## Context

eFact requires stateless authentication for its REST API (`^/api` routes), with the following constraints:

1. **HttpOnly cookie delivery** — Tokens must be stored in HttpOnly, Secure, SameSite cookies to prevent XSS token theft. The standard Authorization Bearer header workflow (used by LexikJWT by default) exposes the token to JavaScript.
2. **Tenant-encoded context** — Every JWT must carry the `tenantName` so that `JWTAuthenticator` can validate that the token was issued for the requesting subdomain. This prevents token reuse across tenants.
3. **Branch context** — After login, the user selects a branch. The selected branch ID must be embedded in the token (or a secondary token issued) so that controllers can derive the data scope without an extra DB lookup.
4. **Refresh token flow** — Access tokens expire short-term; a refresh token flow (`POST /api/auth/refresh`) must issue new access tokens without requiring re-login.
5. **Android POS compatibility** — The Android app (`efact-app`) consumes the same JWT API. The auth flow must work with cookie-based or header-based token delivery, supporting both browser and mobile clients.
6. **Control plane isolation** — The control plane admin (`/admin`) uses a completely different authentication mechanism (`http_basic`) and must not share auth infrastructure with the tenant API.

**LexikJWT evaluation:**

`lexik/jwt-authentication-bundle` was evaluated but rejected because:
- Its default delivery is Authorization Bearer header; adding HttpOnly cookie support requires custom event listeners and significant override work.
- Its `JWTCreatedEvent` / `JWTDecodedEvent` extension points are adequate but require understanding the bundle's internal lifecycle to embed custom claims (`tenantName`, `branchId`).
- It adds a bundle dependency for what is ultimately a thin wrapper around `firebase/php-jwt`, which we already use directly.
- The control plane separation and the tenant-context validation logic are non-standard enough that a custom authenticator gives cleaner, more maintainable code.

---

## Decision

Implement **custom JWT authentication** using `firebase/php-jwt` directly, with the following components:

| Class | Responsibility |
|-------|---------------|
| `JWTManager` | Encodes and decodes JWT payloads; validates signature and expiry |
| `TokenGeneratorService` | Generates access + refresh token pair; embeds `tenantName`, `userId`, `branchId`, `roles` |
| `CookieFactoryService` | Creates HttpOnly, Secure, SameSite=Strict response cookies for access and refresh tokens |
| `JWTAuthenticator` | Symfony `AbstractAuthenticator`; reads token from cookie (primary) or `Authorization: Bearer` header (fallback, for Android); decodes and validates; verifies `tenantName` matches the Host-resolved tenant |
| `RefreshTokenService` | Validates the refresh token cookie; issues a new access token |
| `UserProvider` | Loads the `User` entity from the tenant DB by `userId` claim |

### Token Claims

```json
{
  "sub": "42",
  "tenantName": "acme",
  "branchId": "uuid-of-branch",
  "branchName": "Main Store",
  "roles": ["ROLE_CASHIER"],
  "iat": 1712000000,
  "exp": 1712003600
}
```

### Cookie Delivery

```
Set-Cookie: efact_token=<jwt>; HttpOnly; Secure; SameSite=Strict; Path=/api; Max-Age=3600
Set-Cookie: efact_refresh=<refresh>; HttpOnly; Secure; SameSite=Strict; Path=/api/auth/refresh; Max-Age=604800
```

### Android Fallback

The Android app (`efact-app`) sends the access token as `Authorization: Bearer <jwt>`. `JWTAuthenticator` checks the cookie first, then falls back to the header — no special Android endpoint needed.

### Branch Selection

After login, `POST /api/auth/select-branch` issues a new access token with the selected `branchId` and `branchName` embedded. The old access token is invalidated (refresh token remains).

`BranchContextService` reads `branchId` from the decoded token on each request, making the active branch available to all controllers without a DB lookup.

### Security Firewall Config

```yaml
# config/packages/security.yaml
firewalls:
  admin:
    pattern: ^/admin
    http_basic: ~
    provider: control_plane_provider

  api:
    pattern: ^/api
    stateless: true
    custom_authenticators:
      - App\Core\Security\Auth\JWTAuthenticator

  main:
    lazy: true
    provider: app_user_provider
```

---

## Consequences

### Positive

- **XSS-resistant token storage** — HttpOnly cookies prevent JavaScript from reading tokens, eliminating the most common JWT theft vector.
- **Tenant validation at auth layer** — `JWTAuthenticator` cross-checks `tenantName` from the token against the Host-resolved tenant. A token issued for `acme.efact.pe` cannot authenticate on `beta.efact.pe`.
- **Branch context without DB** — `branchId` in the token means every controller gets the active branch from memory, not from an extra DB query.
- **Full control** — Custom classes are easier to audit, test, and modify than navigating a bundle's extension points.
- **Single auth surface for web + mobile** — Cookie-first with Bearer header fallback covers both browser and Android without separate endpoints.

### Negative

- **We own the code** — Security vulnerabilities in `JWTManager` or `JWTAuthenticator` are our responsibility to fix, not an upstream bundle's.
- **More code to maintain** — `TokenGeneratorService`, `CookieFactoryService`, `RefreshTokenService`, `JWTAuthenticator`, `UserProvider` are all custom. A future developer must understand these rather than referencing LexikJWT docs.
- **No built-in token revocation** — Refresh tokens are stored in the `refresh_tokens` table (custom), but access tokens cannot be revoked before expiry. Token TTL must be short enough for this to be acceptable.

---

## Related ADRs

- ADR-001 — Multi-tenancy (tenant resolution that `JWTAuthenticator` validates against)
- ADR-002 — Dual-database (control plane uses `http_basic`, tenant API uses this JWT system)
