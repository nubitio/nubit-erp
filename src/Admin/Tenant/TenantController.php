<?php

namespace App\Admin\Tenant;

use App\Core\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TenantController extends AbstractController
{
    public function __construct(
        private readonly TenantManager $tenantManager,
    ) {
    }

    #[Route('/admin/tenants', name: 'admin_tenant_index')]
    public function index(): Response
    {
        $tenants = $this->tenantManager->getTenants();

        return $this->render('admin/tenant/index.html.twig', [
            'tenants' => $tenants,
        ]);
    }

    #[Route('/admin/tenants/create', name: 'admin_tenant_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $tenant = [
            'name' => trim((string) $request->request->get('name', '')),
            'connection' => trim((string) $request->request->get('connection', '')),
        ];

        try {
            $this->tenantManager->addTenant($tenant);
            $this->addFlash('success', 'Tenant created');
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (\RuntimeException $exception) {
            $this->addFlash('error', 'Unable to save tenants: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/delete/{id}', name: 'admin_tenant_delete')]
    public function delete(int $id): Response
    {
        $this->tenantManager->deleteTenant($id);
        $this->addFlash('success', 'Tenant removed');

        return $this->redirectToRoute('admin_tenant_index');
    }
}
