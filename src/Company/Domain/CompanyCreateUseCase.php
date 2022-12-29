<?php

namespace App\Company\Domain;

class CompanyCreateUseCase
{
    private CompanyRepositoryInterface $repository;

    public function __construct(CompanyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Company $company): void
    {
        $this->repository->insert($company);
    }
}
