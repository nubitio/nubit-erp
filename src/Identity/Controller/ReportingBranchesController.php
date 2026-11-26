<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Auth\BranchReportScope;
use App\Organization\Entity\Branch;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class ReportingBranchesController
{
    public function __construct(private BranchReportScope $scope) {}

    #[Route('/api/auth/reporting-branches', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(array_map(
            static fn(Branch $branch): array => [
                'id' => $branch->getId(),
                'name' => $branch->getName(),
                'isActive' => $branch->getIsActive(),
            ],
            $this->scope->allowedBranches(),
        ));
    }
}
