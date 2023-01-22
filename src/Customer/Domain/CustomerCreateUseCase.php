<?php

namespace App\Customer\Domain;

class CustomerCreateUseCase
{
    private CustomerRepositoryInterface $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Customer $customer): Customer
    {
        return $this->repository->insert($customer);
    }
}