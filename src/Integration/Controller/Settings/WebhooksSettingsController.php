<?php

declare(strict_types=1);

namespace App\Integration\Controller\Settings;

use App\Integration\Event\EventType;
use App\Integration\Repository\WebhookDeliveryRepository;
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
 * Tenant-facing management of webhook endpoints + a read-only delivery log,
 * behind the JWT firewall and the `settings` permissions. UI counterpart to
 * `app:integration:webhook-endpoint:create` / `:webhooks:replay`.
 */
#[Route('/api/settings/integration/webhook-endpoints')]
final class WebhooksSettingsController extends AbstractController
{
    public function __construct(
        private readonly WebhookEndpointRepository $endpoints,
        private readonly WebhookDeliveryRepository $deliveries,
        private readonly WebhookSecretGenerator $secrets,
        private readonly IntegrationSettingsPresenter $presenter,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('', name: 'settings_integration_webhooks_list', methods: ['GET'])]
    #[IsGranted('perm:settings.view')]
    public function list(): JsonResponse
    {
        return new JsonResponse([
            'event_types' => EventType::all(),
            'data' => array_map($this->presenter->endpoint(...), $this->endpoints->findBy([], ['createdAt' => 'ASC'])),
        ]);
    }

    #[Route('', name: 'settings_integration_webhooks_create', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function create(Request $request): JsonResponse
    {
        $body = $this->decode($request);
        $url = is_string($body['url'] ?? null) ? trim($body['url']) : '';
        if (!str_starts_with($url, 'https://')) {
            return $this->fail('The URL must start with https://');
        }

        $secret = $this->secrets->generate();
        $endpoint = new WebhookEndpoint($url, $secret, $this->types($body['types'] ?? null));
        $this->entityManager->persist($endpoint);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->endpoint($endpoint) + ['secret' => $secret], Response::HTTP_CREATED);
    }

    #[Route('/{id}/rotate-secret', name: 'settings_integration_webhooks_rotate', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function rotateSecret(string $id): JsonResponse
    {
        $endpoint = $this->require($id);
        if (!$endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        $secret = $this->secrets->generate();
        $endpoint->rotateSecret($secret);
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->endpoint($endpoint) + ['secret' => $secret]);
    }

    #[Route('/{id}/toggle', name: 'settings_integration_webhooks_toggle', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function toggle(string $id): JsonResponse
    {
        $endpoint = $this->require($id);
        if (!$endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        if ($endpoint->isEnabled()) {
            $endpoint->disable('disabled from settings');
        } else {
            $endpoint->enable();
        }
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->endpoint($endpoint));
    }

    #[Route('/{id}', name: 'settings_integration_webhooks_delete', methods: ['DELETE'])]
    #[IsGranted('perm:settings.edit')]
    public function delete(string $id): JsonResponse
    {
        $endpoint = $this->require($id);
        if (!$endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        $this->entityManager->remove($endpoint);
        $this->entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/deliveries', name: 'settings_integration_webhooks_deliveries', methods: ['GET'])]
    #[IsGranted('perm:settings.view')]
    public function deliveries(string $id): JsonResponse
    {
        $endpoint = $this->require($id);
        if (!$endpoint instanceof WebhookEndpoint) {
            return $endpoint;
        }

        return new JsonResponse([
            'data' => array_map($this->presenter->delivery(...), $this->deliveries->findRecentForEndpoint($id)),
        ]);
    }

    #[Route('/deliveries/{deliveryId}/retry', name: 'settings_integration_webhooks_retry', methods: ['POST'])]
    #[IsGranted('perm:settings.edit')]
    public function retry(string $deliveryId): JsonResponse
    {
        $delivery = $this->deliveries->find($deliveryId);
        if (null === $delivery) {
            return $this->fail('Delivery not found.', Response::HTTP_NOT_FOUND);
        }

        $delivery->requeue();
        $this->entityManager->flush();

        return new JsonResponse($this->presenter->delivery($delivery));
    }

    /**
     * @return list<string>
     */
    private function types(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $known = EventType::all();
        $types = [];
        foreach ($raw as $type) {
            if (!(is_string($type) && ('*' === $type || in_array($type, $known, true) || str_ends_with($type, '.*')))) {
                continue;
            }

            $types[] = $type;
        }

        return array_values(array_unique($types));
    }

    private function require(string $id): WebhookEndpoint|JsonResponse
    {
        return $this->endpoints->find($id) ?? $this->fail('Endpoint not found.', Response::HTTP_NOT_FOUND);
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
