# Auth boundary isolation

## Decision

The current authentication implementation is **Efact app auth**, not part of the reusable Platform boundary or the future `efact/platform` Composer package.

The implementation has been moved out of legacy `App\Core` namespaces and is now owned by Identity:

- `src/Identity/Auth/*`
- `src/Identity/Security/SecurityService.php`
- `src/Identity/Security/Security.php`
- `src/Identity/EventListener/AppSecretBootListener.php`

The former Core auth/session paths are removed from production code. New code must import the Identity-owned classes directly.

## Why it is app-owned

The current auth stack depends on Efact-specific application types and persistence concerns, including:

- `App\Entity\User`
- `App\Entity\Branch`
- `App\Entity\RefreshToken`
- `App\Dto\Auth\AuthResponseDto` / `AuthUserDto`
- `App\Repository\RefreshTokenRepository`

It also encodes Efact JWT payload shape, branch context, refresh-token behavior, cookie names and Mercure subscriber-token behavior. Those are application/security policy concerns rather than portable Platform capabilities.

## Current location

Symfony security config and service wiring now point at Identity-owned services:

- provider: `App\Identity\Auth\UserProvider`
- authenticator: `App\Identity\Auth\JWTAuthenticator`
- explicit service configuration for `JWTManager`, `TokenGeneratorService`, `CookieFactoryService`, and `MercureSubscriberTokenService` uses `App\Identity\Auth\...`

The migration deliberately did not introduce speculative Platform security contracts. Consumers that need authenticated-user/session behavior depend on `App\Identity\Security\Security`.

## Platform rule

`src/Platform` may define project-agnostic security contracts only when there is a proven reusable need. No such contract is introduced by `TASK-018`; adding one now would be speculative.

The static platform-boundary guard rejects `src/Platform` references to app auth namespaces. `tests/Architecture/ContextBoundaryTest.php` also rejects reintroducing imports of the removed Core auth/session namespaces:

- `App\Core\Security\Auth\*`
- `App\Core\Symfony\Security\*`
- `App\Core\Application\Session\Security`
