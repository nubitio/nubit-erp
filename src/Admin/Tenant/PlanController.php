<?php

declare(strict_types=1);

namespace App\Admin\Tenant;

use App\Core\Audit\AuditLogger;
use App\Core\Service\PlanManager;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class PlanController extends AbstractController
{
    public function __construct(
        private readonly PlanManager $planManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('/admin/plans', name: 'admin_plan_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/plan/index.html.twig', [
            'plans' => $this->planManager->getPlans(),
        ]);
    }

    #[Route('/admin/plans/create', name: 'admin_plan_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_plan_index');
        }

        try {
            $name = strtolower(trim((string)$request->request->get('name', '')));
            $limits = $this->extractLimits($request);
            $isDefault = (bool)$request->request->get('is_default', false);

            $this->planManager->createPlan($name, $limits, $isDefault);
            $this->auditLogger->log('plan.create', ['name' => $name, 'limits' => $limits, 'is_default' => $isDefault]);
            $this->addFlash('success', 'Plan created');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_plan_index');
    }

    #[Route('/admin/plans/{id}', name: 'admin_plan_update', methods: ['POST'])]
    public function update(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_plan_index');
        }

        try {
            $name = strtolower(trim((string)$request->request->get('name', '')));
            $limits = $this->extractLimits($request);
            $isDefault = (bool)$request->request->get('is_default', false);

            $this->planManager->updatePlan($id, $name, $limits, $isDefault);
            $this->auditLogger->log('plan.update', [
                'id' => $id,
                'name' => $name,
                'limits' => $limits,
                'is_default' => $isDefault,
            ]);
            $this->addFlash('success', 'Plan updated');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_plan_index');
    }

    #[Route('/admin/plans/{id}/delete', name: 'admin_plan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('admin', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token. Please try again.');

            return $this->redirectToRoute('admin_plan_index');
        }

        try {
            $this->planManager->deletePlan($id);
            $this->auditLogger->log('plan.delete', ['id' => $id]);
            $this->addFlash('success', 'Plan deleted');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('admin_plan_index');
    }

    /**
     * @return array<string, int>
     */
    private function extractLimits(Request $request): array
    {
        $keys = ['users', 'customers', 'products', 'invoices', 'branches', 'storage_mb'];
        $limits = [];
        foreach ($keys as $key) {
            $limits[$key] = (int)$request->request->get($key, -1);
        }

        return $limits;
    }
}
