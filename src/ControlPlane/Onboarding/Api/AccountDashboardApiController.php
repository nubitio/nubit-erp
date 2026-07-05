<?php

declare(strict_types=1);

namespace App\ControlPlane\Onboarding\Api;

use App\ControlPlane\Onboarding\Service\AccountService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/account')]
final class AccountDashboardApiController extends AbstractController
{
    use AccountSessionTrait;

    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    #[Route('/dashboard', name: 'api_account_dashboard', methods: ['GET'])]
    public function dashboard(Request $request): JsonResponse
    {
        $accountId = $this->requireAccountId($request);
        if ($accountId instanceof JsonResponse) {
            return $accountId;
        }

        $account = $this->accountService->findById($accountId);
        if ($account === null) {
            $this->clearAccountSession($request);

            return $this->json(['error' => 'Cuenta no encontrada.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'account' => [
                'id' => $account['id'],
                'email' => $account['email'],
            ],
            'tenants' => $this->accountService->listAccountTenants($accountId),
            'provisions' => $this->normalizeProvisions($this->accountService->listAccountProvisions($accountId)),
        ]);
    }

    #[Route('/provisions/{provisionId}/dismiss', name: 'api_account_provision_dismiss', methods: ['POST'])]
    public function dismissProvision(Request $request, int $provisionId): JsonResponse
    {
        $accountId = $this->requireAccountId($request);
        if ($accountId instanceof JsonResponse) {
            return $accountId;
        }

        $dismissed = $this->accountService->dismissFailedProvision($accountId, $provisionId);
        if (!$dismissed) {
            return $this->json(['error' => 'Provision no encontrada.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['ok' => true]);
    }
}
