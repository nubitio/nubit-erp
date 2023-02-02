<?php

namespace App\Company\Domain;

class GetOneCompanyUseCase
{
    private CompanyRepositoryInterface $repository;

    public function __construct(CompanyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Company
    {
        return $this->repository->getOne($id);
    }
}