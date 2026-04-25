# Auth boundary isolation

## Decision

The current authentication implementation is **Efact app auth**, not part of the reusable Platform boundary or the future `efact/platform` Composer package.

The implementation remains in its legacy `App\Core` namespaces for now to avoid a broad, risky namespace migration across Symfony security config, controllers, authenticators and service IDs:

- `src/Core/Security/Auth/*`
- `src/Core/Symfony/Security/SecurityService.php`
- `src/Core/Application/Session/Security.php`

These paths are transitional compatibility locations only. They are excluded from the package candidate set and must not be imported from `src/Platform`.

## Why it is app-owned

The current auth stack depends on Efact-specific application types and persistence concerns, including:

- `App\Entity\User`
- `App\Entity\Branch`
- `App\Entity\RefreshToken`
- `App\Dto\Auth\AuthResponseDto` / `AuthUserDto`
- `App\Repository\RefreshTokenRepository`

It also encodes Efact JWT payload shape, branch context, refresh-token behavior, cookie names and Mercure subscriber-token behavior. Those are application/security policy concerns rather than portable Platform capabilities.

## Future target

When the migration is safe, move the concrete implementation to an app-owned namespace such as:

- `src/Auth/*`, or
- `src/Efact/Auth/*`

The move should preserve existing service IDs and Symfony security references with temporary aliases or BC shims, especially for:

- `config/packages/security.yaml`
- `config/services.yaml`
- imports of `JWTAuthenticator`, `JWTManager`, `TokenGeneratorService`, `RefreshTokenService`, `UserProvider`, `SecurityService`, and session `Security`

## Platform rule

`src/Platform` may define project-agnostic security contracts only when there is a proven reusable need. No such contract is introduced by `TASK-018`; adding one now would be speculative.

The static platform-boundary guard rejects new `src/Platform` references to the transitional app auth namespaces:

- `App\Core\Security\Auth\*`
- `App\Core\Symfony\Security\*`
- `App\Core\Application\Session\Security`
