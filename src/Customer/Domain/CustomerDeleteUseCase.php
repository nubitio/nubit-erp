<?php

namespace App\Customer\Domain;

class CustomerDeleteUseCase
{
    private CustomerRepositoryInterface $repository;

    public function __construct(CustomerRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}