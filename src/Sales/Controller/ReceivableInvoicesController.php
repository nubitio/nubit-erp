<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Auth\BranchReportScope;
use App\Sales\Repository\SaleRecordRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class ReceivableInvoicesController
{
    public function __construct(
        private BranchReportScope $scope,
        private SaleRecordRepository $sales,
    ) {}

    #[Route('/api/sales/reports/receivable-customers/{customerId<\d+>}/invoices', methods: ['GET'])]
    #[IsGranted('perm:sales.view')]
    public function __invoke(int $customerId, Request $request): JsonResponse
    {
        $branchId = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);

        return new JsonResponse($this->sales->openReceivableDetails($branchId, $customerId));
    }

    #[Route('/api/sales/reports/receivable-invoices', methods: ['GET'])]
    #[IsGranted('perm:sales.view')]
    public function list(Request $request): JsonResponse
    {
        $branchId = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);

        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(200, $request->query->getInt('itemsPerPage', 20)));
        $invoices = $this->sales->listOpenReceivables($branchId, $page, $perPage);

        return new JsonResponse([
            'hydra:member' => $invoices,
            'hydra:totalItems' => $this->sales->countOpenReceivables($branchId),
        ]);
    }
}
