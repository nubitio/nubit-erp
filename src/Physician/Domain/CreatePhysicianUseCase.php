<?php

namespace App\Physician\Domain;

class CreatePhysicianUseCase
{
    private PhysicianRepositoryInterface $repository;

    public function __construct(PhysicianRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Physician $physician): Physician
    {
        return $this->repository->insert($physician);
    }
}
