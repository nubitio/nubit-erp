<?php

namespace App\Employee\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Employee>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
