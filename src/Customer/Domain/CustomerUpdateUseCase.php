<?php

namespace App\Customer\Domain;

class CustomerUpdateUseCase
{
    private CustomerRepositoryInterface $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Customer $customer): Customer
    {
        return $this->repository->update($customer);
    }
}