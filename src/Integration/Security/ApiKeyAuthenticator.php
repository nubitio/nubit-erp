<?php

declare(strict_types=1);

namespace App\Integration\Security;

use App\Integration\Repository\ApiKeyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Contract\TenantRegistryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Authenticates `/api/v1` requests carrying `Authorization: Bearer nb_<env>_<tenant>_<secret>`.
 *
 * The key names its own tenant, so this switches the database connection to
 * that tenant *before* it can look the key row up — there is no shared table.
 * A valid key yields an {@see ApiClientUser} whose roles are the client's
 * scopes.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    private const string PREFIX = '/api/v1';

    public function __construct(
        private readonly ApiKeyGenerator $keys,
        private readonly ApiKeyRepository $apiKeys,
        private readonly TenantRegistryInterface $tenantRegistry,
        private readonly TenantConnectionSwitcherInterface $tenantSwitcher,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly ?TenantContext $tenantContext = null,
    ) {}

    public function supports(Request $request): ?bool
    {
        if (!str_starts_with($request->getPathInfo(), self::PREFIX)) {
            return false;
        }

        return null !== $this->bearer($request);
    }

    public function authenticate(Request $request): Passport
    {
        $key = $this->bearer($request);
        if (null === $key) {
            throw new CustomUserMessageAuthenticationException('Missing API key.');
        }

        $parsed = $this->keys->parse($key);
        if (null === $parsed) {
            throw new CustomUserMessageAuthenticationException('Malformed API key.');
        }

        $tenantRow = $this->tenantRegistry->getTenantByName($parsed['tenant']);
        if (null === $tenantRow) {
            $this->logger->warning('integration.auth.unknown_tenant', ['tenant' => $parsed['tenant']]);

            throw new CustomUserMessageAuthenticationException('Invalid API key.');
        }

        $this->tenantSwitcher->switchConnection($parsed['tenant']);

        /** @var int|string|null $rawId */
        $rawId = $tenantRow['id'] ?? null;
        /** @var string|null $rawDomain */
        $rawDomain = $tenantRow['primary_domain'] ?? null;
        $this->tenantContext?->setTenant(
            null !== $rawId ? (int) $rawId : null,
            $parsed['tenant'],
            null !== $rawDomain ? $rawDomain : null,
            (string) $request->attributes->get('request_id', ''),
        );
        $this->tenantContext?->setActor('api-key', 'api');

        $apiKey = $this->apiKeys->findOneByPrefix($parsed['prefix']);
        if (null === $apiKey || !hash_equals($apiKey->getTokenHash(), $parsed['tokenHash'])) {
            throw new CustomUserMessageAuthenticationException('Invalid API key.');
        }
        if ($apiKey->isRevoked()) {
            throw new CustomUserMessageAuthenticationException('This API key has been revoked.');
        }

        $client = $apiKey->getClient();
        $user = new ApiClientUser(
            clientId: (string) $client->getId(),
            clientName: $client->getName(),
            tenant: $parsed['tenant'],
            scopes: $client->getScopes(),
        );

        if ($apiKey->touch(new \DateTimeImmutable())) {
            try {
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                // last_used_at is best-effort telemetry; never fail auth on it.
                $this->logger->notice('integration.auth.touch_failed', ['error' => $e->getMessage()]);
            }
        }

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), static fn(): ApiClientUser => $user),
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        #[\SensitiveParameter]
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return $this->deny($exception->getMessageKey());
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->deny('Authentication required.');
    }

    private function bearer(Request $request): ?string
    {
        $header = $request->headers->get('Authorization') ?? '';
        if (!str_starts_with($header, 'Bearer nb_')) {
            return null;
        }

        return substr($header, offset: 7);
    }

    private function deny(string $message): JsonResponse
    {
        return new JsonResponse(['error' => [
            'code' => 'unauthenticated',
            'message' => $message,
        ]], Response::HTTP_UNAUTHORIZED);
    }
}
