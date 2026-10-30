<?php

declare(strict_types=1);

namespace App\AccountsPayable\Controller;

use App\AccountsPayable\Report\SupplierBillSummaryQuery;
use App\Identity\Auth\BranchContextServiceInterface;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class SupplierBillSummaryController extends AbstractController
{
    public function __construct(
        private readonly SupplierBillSummaryQuery $summary,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    public function __invoke(): JsonResponse
    {
        $branch = $this->branchContext->getCurrentBranchId();
        if ($branch === null) {
            throw new BadRequestHttpException('Selecciona una sucursal para consultar sus vencimientos.');
        }
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));

        return $this->json([
            'asOf' => $today->format('Y-m-d'),
            'currencies' => $this->summary->execute($branch, $today),
        ]);
    }
}
