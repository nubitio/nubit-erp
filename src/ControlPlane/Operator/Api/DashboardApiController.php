<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Api;

use App\ControlPlane\Dashboard\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/api/control-plane/dashboard')]
final class DashboardApiController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    #[Route('', name: 'api_control_plane_dashboard', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            'tenantStats' => $this->dashboardService->getTenantStats(),
            'planStats' => $this->dashboardService->getPlanStats(),
            'backupStats' => $this->dashboardService->getBackupStats(),
            'auditSummary' => $this->dashboardService->getRecentAuditSummary(),
        ]);
    }
}
