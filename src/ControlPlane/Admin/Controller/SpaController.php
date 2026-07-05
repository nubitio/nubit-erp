<?php

declare(strict_types=1);

namespace App\ControlPlane\Admin\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the React control-plane SPA for all /admin routes except login.
 */
#[IsGranted('ROLE_CP_VIEWER')]
final class SpaController extends AbstractController
{
    #[Route('/admin/tenants', name: 'admin_tenant_index', methods: ['GET'])]
    #[Route(
        '/admin/tenants/{path}',
        name: 'admin_spa_tenants_nested',
        requirements: ['path' => '.+'],
        methods: ['GET'],
    )]
    #[Route('/admin/plans', name: 'admin_spa_plans', methods: ['GET'])]
    #[Route('/admin/plans', name: 'admin_plan_index', methods: ['GET'])]
    #[Route('/admin/plan-features', name: 'admin_spa_plan_features', methods: ['GET'])]
    #[Route('/admin/plan-features', name: 'admin_plan_features_index', methods: ['GET'])]
    #[Route('/admin/audit-log', name: 'admin_spa_audit', methods: ['GET'])]
    #[Route('/admin/audit-log', name: 'admin_audit_log_index', methods: ['GET'])]
    #[Route('/admin/users', name: 'admin_spa_users', methods: ['GET'])]
    #[Route('/admin/users', name: 'admin_user_index', methods: ['GET'])]
    #[Route('/admin/users/{path}', name: 'admin_spa_users_nested', requirements: ['path' => '.+'], methods: ['GET'])]
    public function nested(): Response
    {
        return $this->render('admin/spa.html.twig');
    }
}
