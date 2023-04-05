<?php

namespace App\Branch\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

class BranchGetAllUseCase
{
    private BranchRepositoryInterface $repository;

    public function __construct(BranchRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<Branch>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}