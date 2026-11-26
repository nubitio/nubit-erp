<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Identity\Auth\BranchReportScope;
use App\Inventory\Service\InventorySummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('perm:inventory.view')]
#[Route('/api/inventory/summary', name: 'api_inventory_summary', methods: ['GET'])]
final class InventorySummaryController extends AbstractController
{
    public function __construct(
        private readonly InventorySummaryService $summaryService,
        private readonly BranchReportScope $scope,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $warehouseId = $request->query->getString('warehouseId');
        $branchId = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);

        return $this->json($this->summaryService->getSummary($branchId, $warehouseId !== '' ? $warehouseId : null));
    }
}
