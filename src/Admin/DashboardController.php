<?php

declare(strict_types=1);

namespace App\Admin;

use App\Core\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_CP_VIEWER')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {
    }

    #[Route('/admin', name: 'admin_dashboard')]
    public function index(): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'tenant_stats' => $this->dashboardService->getTenantStats(),
            'plan_stats' => $this->dashboardService->getPlanStats(),
            'audit_summary' => $this->dashboardService->getRecentAuditSummary(),
            'backup_stats' => $this->dashboardService->getBackupStats(),
        ]);
    }
}
