<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final readonly class OperatingBranchesController
{
    public function __construct(
        private BranchRepository $branches,
    ) {}

    #[Route('/api/auth/operating-branches', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function __invoke(#[CurrentUser] User $user): JsonResponse
    {
        $options = [];
        foreach ($this->branches->findBy(['isActive' => true], ['name' => 'ASC']) as $branch) {
            $id = $branch->getId();
            if ($id === null || !$user->canOperateInBranch($id)) {
                continue;
            }

            $isDefault = $id === $user->getDefaultBranchId();
            $option = ['id' => $id, 'name' => $branch->getName(), 'isDefault' => $isDefault];
            if ($isDefault) {
                array_unshift($options, $option);
            } else {
                $options[] = $option;
            }
        }

        return new JsonResponse($options);
    }
}
