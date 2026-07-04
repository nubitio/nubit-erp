<?php

declare(strict_types=1);

namespace App\ControlPlane\Tenant\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Legacy URL redirects for bookmarks and external links.
 * All mutations are handled by /api/control-plane/tenants/*.
 */
#[IsGranted('ROLE_CP_VIEWER')]
#[Route('/admin/tenants')]
final class TenantController extends AbstractController
{
    #[Route('/{id}/certificates', name: 'admin_tenant_certificates', methods: ['GET'])]
    public function certificatesRedirect(int $id): Response
    {
        return $this->redirect("/admin/tenants/{$id}?tab=certificates");
    }

    #[Route('/{id}/certificates/{certificateId}', name: 'admin_tenant_certificate_detail', methods: ['GET'], requirements: ['certificateId' => '\d+'])]
    public function certificateDetailRedirect(int $id, int $certificateId): Response
    {
        return $this->redirect("/admin/tenants/{$id}?tab=certificates&certificate={$certificateId}");
    }

    #[Route('/{id}/edit', name: 'admin_tenant_edit', methods: ['GET'])]
    public function editRedirect(int $id): Response
    {
        return $this->redirect("/admin/tenants/{$id}");
    }
}