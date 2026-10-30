<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Auth\BranchContextServiceInterface;
use App\Sales\Report\Summary\MonthlySalesSummary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class MonthlySalesSummaryController extends AbstractController
{
    public function __construct(
        private readonly MonthlySalesSummary $summary,
        private readonly BranchContextServiceInterface $branchContext,
    ) {}

    public function __invoke(): JsonResponse
    {
        $branchId = $this->branchContext->getCurrentBranchId();
        if ($branchId === null) {
            throw new BadRequestHttpException('Selecciona una sucursal para consultar el resumen.');
        }

        return $this->json($this->summary->get($branchId));
    }
}
