<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Service\TenantManager;
use App\Core\Service\GitHubWorkflowDispatcher;
use App\Core\Audit\AuditLogger;
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
        private readonly AuditLogger $auditLogger,
        private readonly GitHubWorkflowDispatcher $workflowDispatcher,
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

        $domains = $this->tenantManager->getTenantDomains($id);
        $primaryDomain = $this->tenantManager->getPrimaryDomain($id);

        return $this->render('admin/tenant/edit.html.twig', [
            'tenant' => $tenant,
            'domains' => $domains,
            'primary_domain' => $primaryDomain,
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
            $this->auditLogger->log('tenant.create', ['tenant' => $tenant]);
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
            $this->auditLogger->log('tenant.update', ['tenant_id' => $id, 'tenant' => $tenant]);
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
        $this->auditLogger->log('tenant.delete', ['tenant_id' => $id]);
        $this->addFlash('success', 'Tenant removed');

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/{id}/domains', name: 'admin_tenant_domain_create', methods: ['POST'])]
    public function addDomain(int $id, Request $request): Response
    {
        $domain = trim((string)$request->request->get('domain', ''));
        $isPrimary = (bool)$request->request->get('is_primary', false);

        try {
            $this->tenantManager->addTenantDomain($id, $domain, $isPrimary);
            $this->auditLogger->log('tenant.domain.create', [
                'tenant_id' => $id,
                'domain' => $domain,
                'is_primary' => $isPrimary,
            ]);
            $this->addFlash('success', 'Domain added');
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to add domain: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }

    #[Route(
        '/admin/tenants/{tenantId}/domains/{domainId}/primary',
        name: 'admin_tenant_domain_primary',
        methods: ['POST']
    )]
    public function setPrimaryDomain(int $tenantId, int $domainId): Response
    {
        try {
            $this->tenantManager->setPrimaryTenantDomain($tenantId, $domainId);
            $this->auditLogger->log('tenant.domain.primary', [
                'tenant_id' => $tenantId,
                'domain_id' => $domainId,
            ]);
            $this->addFlash('success', 'Primary domain updated');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to update primary domain: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
    }

    #[Route('/admin/tenants/{tenantId}/domains/{domainId}/delete', name: 'admin_tenant_domain_delete')]
    public function deleteDomain(int $tenantId, int $domainId): Response
    {
        $this->tenantManager->deleteTenantDomain($domainId);
        $this->auditLogger->log('tenant.domain.delete', [
            'tenant_id' => $tenantId,
            'domain_id' => $domainId,
        ]);
        $this->addFlash('success', 'Domain removed');

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $tenantId]);
    }

    #[Route('/admin/tenants/{id}/provision', name: 'admin_tenant_provision', methods: ['POST'])]
    public function provision(int $id, Request $request): Response
    {
        $tenant = $this->tenantManager->getTenant($id);
        if ($tenant === null) {
            $this->addFlash('error', 'Tenant not found');

            return $this->redirectToRoute('admin_tenant_index');
        }

        $adminUser = trim((string)$request->request->get('admin_user', 'admin'));
        $domain = trim((string)$request->request->get('domain', ''));

        if ($domain === '') {
            $domain = (string)($this->tenantManager->getPrimaryDomain($id) ?? '');
        }

        try {
            $this->workflowDispatcher->dispatch([
                'tenant_name' => (string)$tenant['name'],
                'domain' => $domain,
                'admin_user' => $adminUser,
            ]);
            $this->auditLogger->log('tenant.provision.dispatch', [
                'tenant_id' => $id,
                'domain' => $domain,
            ]);
            $this->addFlash('success', 'Provisioning workflow dispatched.');
        } catch (RuntimeException $exception) {
            $this->addFlash('error', 'Unable to dispatch provisioning: ' . $exception->getMessage());
        }

        return $this->redirectToRoute('admin_tenant_edit', ['id' => $id]);
    }
}
