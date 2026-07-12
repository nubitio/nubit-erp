<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Audit\AuditLogger;
use App\Identity\Auth\UserProvider;
use Nubit\AdminBundle\Auth\TokenGenerator;
use Nubit\Platform\Tenant\Runtime\TenantRuntime;
use Nubit\Platform\Tenant\Runtime\TenantRuntimeActor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

/**
 * Support impersonation: mints a short-lived access token for a tenant end
 * user, on behalf of a control-plane super admin, so support can reproduce
 * an issue "as" the customer. Gated to ROLE_SUPER_ADMIN, fully audited, and
 * never hands out a refresh token — the admin must re-impersonate rather
 * than silently keep a support session alive.
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ImpersonationApiController extends AbstractController
{
    public function __construct(
        private readonly TenantService $tenantService,
        private readonly TenantRuntime $tenantRuntime,
        private readonly UserProvider $userProvider,
        private readonly TokenGenerator $tokenGenerator,
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route(
        '/api/control-plane/tenants/{id}/impersonate',
        name: 'api_control_plane_tenants_impersonate',
        requirements: ['id' => '\d+'],
        methods: ['POST'],
    )]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantService->getTenant($id);
        if ($tenant === null) {
            return $this->json(['error' => 'Tenant not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];
        $username = trim((string) ($payload['username'] ?? ''));
        if ($username === '') {
            return $this->json(['error' => 'Username is required'], Response::HTTP_BAD_REQUEST);
        }

        $admin = $this->getUser()?->getUserIdentifier() ?? 'control-plane-api';

        try {
            $tokenPair = $this->tenantRuntime->run(
                $tenant,
                function () use ($username, $admin) {
                    $user = $this->userProvider->loadUserByIdentifier($username);

                    return $this->tokenGenerator->generateTokenPair($user, [
                        'impersonatedBy' => $admin,
                    ]);
                },
                new TenantRuntimeActor(
                    actorIdentifier: $admin,
                    channel: 'control_plane_impersonation',
                ),
            );
        } catch (UserNotFoundException) {
            return $this->json(['error' => 'User not found in tenant'], Response::HTTP_NOT_FOUND);
        } catch (Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $this->auditLogger->log('user.impersonate', [
            'tenant_id' => $id,
            'tenant_name' => (string) $tenant['name'],
            'target_username' => $username,
        ], $id, $admin);

        return $this->json([
            'ok' => true,
            'token' => $tokenPair->accessToken,
            'expiresAt' => $tokenPair->accessTokenExpiresAt,
            'user' => $tokenPair->userData,
        ]);
    }
}
