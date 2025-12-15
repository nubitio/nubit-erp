<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Security\Auth\BranchContextService;
use App\Repository\DashboardRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardController extends AbstractController
{
    public function __construct(
        private readonly DashboardRepository $repository,
        private readonly BranchContextService $branchContext,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $type = $request->attributes->get('type');

        $branchId = $this->branchContext->getCurrentBranchId();

        if (!$branchId) {
            return $this->json([]);
        }

        if ($type == 'overview') {
            $data = $this->repository->getOverview($branchId);
        }

        return $this->json($data ?? []);
    }
}
