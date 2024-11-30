<?php

namespace App\Department\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllDepartmentUseCase
{
    public function __construct(
        private DepartmentRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Department>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
