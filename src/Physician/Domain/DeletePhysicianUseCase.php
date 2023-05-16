<?php

namespace App\Physician\Domain;

class DeletePhysicianUseCase
{
    private PhysicianRepositoryInterface $repository;

    public function __construct(PhysicianRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
