<?php

namespace App\Company\Domain;

class CreateCompanyUseCase
{
    private CompanyRepositoryInterface $repository;

    public function __construct(CompanyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Company $company): Company
    {
        return $this->repository->insert($company);
    }
}
