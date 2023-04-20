<?php

namespace App\Physician\Domain;

class UpdatePhysicianUseCase
{
    private PhysicianRepositoryInterface $repository;

    public function __construct(PhysicianRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Physician $physician): Physician
    {
        return $this->repository->update($physician);
    }
}