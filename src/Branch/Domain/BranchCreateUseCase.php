<?php

namespace App\Branch\Domain;

class BranchCreateUseCase
{
    private BranchRepositoryInterface $repository;

    public function __construct(BranchRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Branch $branch): Branch
    {
        return $this->repository->insert($branch);
    }
}