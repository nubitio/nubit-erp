<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final readonly class BranchReportScope
{
    public function __construct(
        private Security $security,
        private BranchContextServiceInterface $context,
        private BranchRepository $branches,
    ) {}

    /** @return list<Branch> */
    public function allowedBranches(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException();
        }

        $ids = $user->getOperatingBranchIds() ?? array_filter([$this->context->getCurrentBranchId()]);
        $ids = array_values(array_unique([...$ids, ...$user->getReadOnlyBranchIds()]));

        return $ids === [] ? [] : $this->branches->findBy(['id' => $ids], ['name' => 'ASC']);
    }

    public function requireBranchId(?string $requestedBranchId): string
    {
        $id = $requestedBranchId ?? $this->context->getCurrentBranchId();
        if ($id === null || $id === '') {
            throw new BadRequestHttpException('Selecciona una sucursal para consultar el reporte.');
        }

        foreach ($this->allowedBranches() as $branch) {
            if ($branch->getId() === $id) {
                return $id;
            }
        }

        throw new AccessDeniedHttpException('No tienes permiso para consultar esta sucursal.');
    }
}
