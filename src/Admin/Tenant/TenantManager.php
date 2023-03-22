<?php

namespace App\Admin\Tenant;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TenantManager extends AbstractController
{
    #[Route('/admin/tenants', name: 'admin_tenant_index')]
    public function index(): Response
    {
        if (!file_exists($this->getParameter('kernel.project_dir') . '/config/tenants.json')) {
            file_put_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json', '[]');
        }
        $tenants = json_decode(file_get_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json'), true);

        return $this->render('admin/tenant/index.html.twig', [
            'tenants' => $tenants
        ]);
    }

    #[Route('/admin/tenants/create', name: 'admin_tenant_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $data = json_decode(file_get_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json'), true);

        $data[] = [
            'id' => count($data) + 1,
            'name' => $request->get('name'),
            'connection' => $request->get('connection')
        ];

        file_put_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json', json_encode($data));

        return $this->redirectToRoute('admin_tenant_index');
    }

    #[Route('/admin/tenants/delete/{id}', name: 'admin_tenant_delete')]
    public function delete(int $id): Response
    {
        $data = json_decode(file_get_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json'), true);

        foreach ($data as $key => $tenant) {
            if ($tenant['id'] === $id) {
                unset($data[$key]);
            }
        }

        file_put_contents($this->getParameter('kernel.project_dir') . '/config/tenants.json', json_encode($data));

        return $this->redirectToRoute('admin_tenant_index');
    }
}