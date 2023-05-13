<?php

namespace App\Employee\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllEmployeeUseCase
{
    public function __construct(
        private EmployeeRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Employee>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
