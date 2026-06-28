<?php

declare(strict_types=1);

namespace App\Inventory\Controller;

use App\Inventory\Service\KardexSummaryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/api/inventory/kardex-summary', name: 'api_inventory_kardex_summary', methods: ['GET'])]
final class KardexSummaryController extends AbstractController
{
    public function __construct(
        private readonly KardexSummaryService $summaryService,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $productId = $request->query->getString('productId');
        if ($productId === '') {
            return $this->json(['message' => 'productId is required'], Response::HTTP_BAD_REQUEST);
        }

        $warehouseId = $request->query->getString('warehouseId');
        $from = $request->query->getString('from');
        $to = $request->query->getString('to');
        $valuation = $request->query->getString('valuation');

        return $this->json($this->summaryService->getSummary(
            $productId,
            $warehouseId !== '' ? $warehouseId : null,
            $from !== '' ? $from : null,
            $to !== '' ? $to : null,
            $valuation !== '' ? $valuation : null,
        ));
    }
}