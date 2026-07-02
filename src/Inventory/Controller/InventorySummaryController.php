<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Service\InventorySummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/inventory/summary', name: 'api_inventory_summary', methods: ['GET'])]
final class InventorySummaryController extends AbstractController
{
    public function __construct(
        private readonly InventorySummaryService $summaryService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $warehouseId = $request->query->getString('warehouseId');
        $normalizedWarehouseId = $warehouseId !== '' ? $warehouseId : null;

        return $this->json($this->summaryService->getSummary($normalizedWarehouseId));
    }
}
