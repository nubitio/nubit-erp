<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Operator\Application\TenantNotificationQueryService;
use App\ControlPlane\Tenant\Application\TenantService;
use App\ControlPlane\Tenant\Service\TenantNotificationService;
use App\Efact\Audit\AuditLogger;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/tenants/{id}/notifications', requirements: ['id' => '\d+'])]
final class TenantNotificationApiController extends AbstractController
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantNotificationQueryService $queryService,
        private readonly TenantNotificationService $notificationService,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('', name: 'api_control_plane_tenants_notifications_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        if ($this->tenantService->getTenant($id) === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->queryService->getMatrix($id));
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('', name: 'api_control_plane_tenants_notifications_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        try {
            $targets = $this->queryService->parseTargetsPayload($payload);
            $this->notificationService->saveTenantTargets($id, $targets);
            $this->auditLogger->log('tenant.notifications.update', [
                'tenant_id' => $id,
                'tenant_name' => (string) ($tenant['name'] ?? ''),
            ]);
        } catch (InvalidArgumentException $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['ok' => true]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/test', name: 'api_control_plane_tenants_notifications_test', methods: ['POST'])]
    public function test(int $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $eventKey = trim((string) ($payload['eventKey'] ?? $payload['event_key'] ?? ''));
        $channel = trim((string) ($payload['channel'] ?? ''));

        if ($eventKey === '' || $channel === '') {
            return $this->json(['error' => 'Event and channel are required'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $sent = $this->notificationService->dispatchTestTargetByTenantId(
                tenantId: $id,
                eventKey: $eventKey,
                channel: $channel,
                tenantName: (string) ($tenant['name'] ?? ''),
            );

            return $this->json([
                'ok' => true,
                'sent' => $sent,
                'message' => $sent
                    ? sprintf('Test notification sent for "%s" via "%s".', $eventKey, $channel)
                    : sprintf(
                        'No test notification sent for "%s" via "%s" (check enable flag and recipients).',
                        $eventKey,
                        $channel,
                    ),
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
