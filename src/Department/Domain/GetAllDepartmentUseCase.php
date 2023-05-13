<?php

namespace App\Department\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllDepartmentUseCase
{
    public function __construct(
        private DepartmentRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Department>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
