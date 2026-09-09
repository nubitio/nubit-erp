<?php

declare(strict_types=1);

namespace App\Integration\Controller\Settings;

use App\Integration\Entity\ApiClient;
use App\Integration\Entity\ApiKey;
use App\Integration\Repository\ApiClientRepository;
use App\Integration\Repository\ApiKeyRepository;
use App\Integration\Security\ApiKeyGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tenant-facing management of integration API keys, behind the normal JWT
 * firewall and the `settings` permissions — this is the UI counterpart to the
 * `app:integration:api-key:create` command. Secrets are shown once, on create
 * and rotate.
 */
#[Route('/api/settings/integration/api-keys')]
final class ApiKeysSettingsController extends AbstractController
{
    /** Scopes a tenant may grant from the UI. */
    private const array GRANTABLE_SCOPES = ['documents:write', 'documents:read', 'webhooks:manage'];

    public function __construct(
        private readonly ApiClientRepository $clients,
        private readonly ApiKeyRepository $keys,
        private readonly ApiKeyGenerator $generator,
        private readonly IntegrationSettingsPresenter $presenter,
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
    ) {}

    #[Route('', name: 'settings_integration_api_keys_list', methods: ['GET'])]
    #[IsGranted('perm:settings.view')]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'grantable_scopes' => self::GRANTABLE_SCOPES,
            'data' => array_map($this->presenter->client(...), $this->clients->findBy([], ['createdAt' => 'ASC'])),
        ]);
    }

    #[Route('', name: 'settings_integration_api_keys_create', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function create(Request $request): JsonResponse
    {
        $body = $this->decode($request);
        $name = is_string($body['name'] ?? null) ? trim($body['name']) : '';
        $scopes = $this->scopes($body['scopes'] ?? null);

        if ('' === $name || [] === $scopes) {
            return $this->fail('A name and at least one scope are required.');
        }

        $client = new ApiClient($name, $scopes);
        $material = $this->generator->generate($this->tenant());
        new ApiKey($client, $material->prefix, $material->tokenHash);

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return new JsonResponse(
            $this->presenter->client($client) + ['secret' => $material->secret],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{clientId}/rotate', name: 'settings_integration_api_keys_rotate', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function rotate(string $clientId): JsonResponse
    {
        $client = $this->clients->find($clientId);
        if (null === $client) {
            return $this->fail('Client not found.', Response::HTTP_NOT_FOUND);
        }

        $material = $this->generator->generate($this->tenant());
        new ApiKey($client, $material->prefix, $material->tokenHash);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->client($client) + ['secret' => $material->secret]);
    }

    #[Route('/keys/{keyId}', name: 'settings_integration_api_keys_revoke', methods: ['DELETE'])]
    #[IsGranted('perm:settings.edit')]
    public function revoke(string $keyId): JsonResponse
    {
        $key = $this->keys->find($keyId);
        if (null === $key) {
            return $this->fail('Key not found.', Response::HTTP_NOT_FOUND);
        }

        $key->revoke();
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->key($key));
    }

    /**
     * @return list<string>
     */
    private function scopes(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $scopes = [];
        foreach ($raw as $scope) {
            if (!(is_string($scope) && in_array($scope, self::GRANTABLE_SCOPES, true))) {
                continue;
            }

            $scopes[] = $scope;
        }

        return array_values(array_unique($scopes));
    }

    private function tenant(): string
    {
        $name = $this->tenantContext->getTenantName();
        if (null === $name || '' === $name) {
            throw new \RuntimeException('No tenant in context.');
        }

        return $name;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Request $request): array
    {
        $decoded = json_decode($request->getContent(), associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    private function fail(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $message]], $status);
    }
}
