<?php

namespace App\Customer\Domain;

class CustomerGetAllUseCase
{
    private CustomerRepositoryInterface $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
