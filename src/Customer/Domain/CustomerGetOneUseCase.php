<?php

namespace App\Customer\Domain;

class CustomerGetOneUseCase
{
    private CustomerRepositoryInterface $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Customer
    {
        return $this->repository->getOne($id);
    }
}