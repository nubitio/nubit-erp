<?php

declare(strict_types=1);

namespace App\Integration\Controller\Api\V1;

use App\Integration\Repository\WebhookEndpointRepository;
use App\Integration\Webhook\WebhookEndpoint;
use App\Integration\Webhook\WebhookSecretGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Self-service management of a tenant's webhook endpoints. The signing secret
 * is returned only on create and rotate.
 */
#[IsGranted('ROLE_SCOPE_WEBHOOKS_MANAGE')]
final class WebhookEndpointsController extends AbstractController
{
    public function __construct(
        private readonly WebhookEndpointRepository $endpoints,
        private readonly WebhookSecretGenerator $secrets,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/api/v1/webhook-endpoints', name: 'integration_v1_webhook_endpoints_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map($this->represent(...), $this->endpoints->findAll()),
        ]);
    }

    #[Route('/api/v1/webhook-endpoints', name: 'integration_v1_webhook_endpoints_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), associative: true);
        if (!is_array($body)) {
            return $this->fail('the request body must be a JSON object');
        }

        $url = is_string($body['url'] ?? null) ? $body['url'] : '';
        if (!str_starts_with($url, 'https://')) {
            return $this->fail('url must be an https URL');
        }

        $types = [];
        $rawTypes = $body['types'] ?? null;
        if (is_array($rawTypes)) {
            foreach ($rawTypes as $type) {
                if (is_string($type) && '' !== trim($type)) {
                    $types[] = trim($type);
                }
            }
        }

        $secret = $this->secrets->generate();
        $endpoint = new WebhookEndpoint($url, $secret, $types);
        $this->entityManager->persist($endpoint);
        $this->entityManager->flush();

        return new JsonResponse($this->represent($endpoint) + ['secret' => $secret], Response::HTTP_CREATED);
    }

    #[Route('/api/v1/webhook-endpoints/{id}', name: 'integration_v1_webhook_endpoints_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $endpoint = $this->endpoints->find($id);
        if (null === $endpoint) {
            return $this->fail('not found', Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($endpoint);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/v1/webhook-endpoints/{id}/rotate-secret',
        name: 'integration_v1_webhook_endpoints_rotate',
        methods: ['POST'],
    )]
    public function rotateSecret(string $id): JsonResponse
    {
        $endpoint = $this->endpoints->find($id);
        if (null === $endpoint) {
            return $this->fail('not found', Response::HTTP_NOT_FOUND);
        }

        $secret = $this->secrets->generate();
        $endpoint->rotateSecret($secret);
        $this->entityManager->flush();

        return new JsonResponse($this->represent($endpoint) + ['secret' => $secret]);
    }

    /**
     * @return array<string, mixed>
     */
    private function represent(WebhookEndpoint $endpoint): array
    {
        return [
            'id' => $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'types' => $endpoint->getSubscribedTypes(),
            'status' => $endpoint->isEnabled() ? 'enabled' : 'disabled',
            'disabled_reason' => $endpoint->getDisabledReason(),
            'consecutive_failures' => $endpoint->getConsecutiveFailures(),
            'created_at' => $endpoint->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    private function fail(string $message, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'validation_failed', 'message' => $message]], $status);
    }
}
