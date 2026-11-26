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
}
