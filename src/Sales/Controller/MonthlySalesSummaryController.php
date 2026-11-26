<?php

declare(strict_types=1);

namespace App\Sales\Controller;

use App\Identity\Auth\BranchReportScope;
use App\Sales\Report\Summary\MonthlySalesSummary;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class MonthlySalesSummaryController extends AbstractController
{
    public function __construct(
        private readonly MonthlySalesSummary $summary,
        private readonly BranchReportScope $scope,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $branchId = $this->scope->requireBranchId($request->query->getString('branchId') ?: null);

        return $this->json($this->summary->get($branchId));
    }
}
