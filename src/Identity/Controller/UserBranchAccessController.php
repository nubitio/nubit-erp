<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserBranchAccessController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly BranchRepository $branches,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    #[Route('/api/users/{id<\d+>}/branch-access', methods: ['GET', 'PUT'])]
    #[IsGranted('perm:users.edit')]
    #[IsGranted('perm:roles.assign')]
    public function __invoke(int $id, Request $request): JsonResponse
    {
        $user = $this->users->find($id);
        if (!$user instanceof User || $user->getDeletedAt() !== null) {
            throw $this->createNotFoundException();
        }

        if ($user->getRole()->getId() === 1) {
            $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');
        }

        if ($request->isMethod('PUT')) {
            $data = $request->toArray();
            $ids = $data['operatingBranchIds'] ?? null;
            $readOnlyIds = $data['readOnlyBranchIds'] ?? $user->getReadOnlyBranchIds();
            $default = $data['defaultBranchId'] ?? null;

            if (!is_array($ids) || !array_is_list($ids) || !is_array($readOnlyIds) || !array_is_list($readOnlyIds)
                || ($default !== null && (!is_string($default) || !in_array($default, $ids, true)))) {
                return $this->invalidAssignment();
            }

            foreach ([...$ids, ...$readOnlyIds] as $branchId) {
                if (!is_string($branchId) || $branchId === '' || !$this->branches->find($branchId) instanceof Branch) {
                    return $this->invalidAssignment();
                }
            }

            $user->assignOperatingBranches(array_values(array_unique($ids)), $default);
            $user->assignReadOnlyBranches(array_values(array_unique($readOnlyIds)));
            $this->entityManager->flush();
        }

        return $this->json([
            'operatingBranchIds' => $user->getOperatingBranchIds(),
            'readOnlyBranchIds' => $user->getReadOnlyBranchIds(),
            'defaultBranchId' => $user->getDefaultBranchId(),
        ]);
    }

    private function invalidAssignment(): JsonResponse
    {
        return $this->json([
            'message' => 'Selecciona sucursales válidas y una sucursal predeterminada entre las asignadas.',
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
