<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Service\TenantManager;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/admin/tenants/{id}/edit', name: 'admin_tenant_edit', methods: ['GET'])]
    public function edit(int $id): Response
    {
        $tenant = $this->tenantManager->getTenant($id);

        if (null === $tenant) {
            $this->addFlash('error', 'Tenant not found');

            return $this->redirectToRoute('admin_tenant_index');
        }

        return $this->render('admin/tenant/edit.html.twig', [
            'tenant' => $tenant,
        ]);
    }

    #[Route('/admin/tenants/create', name: 'admin_tenant_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $tenant = [
            'name' => trim((string)$request->request->get('name', '')),
            'connection' => trim((string)$request->request->get('connection', '')),
        ];

        try {
            $this->tenantManager->addTenant($tenant);
            $this->addFlash('success', 'Tenant created');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to save tenants: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}', name: 'admin_tenant_update', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        $tenant = [
            'name' => trim((string)$request->request->get('name', '')),
            'connection' => trim((string)$request->request->get('connection', '')),
        ];

        try {
            $this->tenantManager->updateTenant($id, $tenant);
            $this->addFlash('success', 'Tenant updated');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
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
