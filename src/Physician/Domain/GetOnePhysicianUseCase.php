<?php

namespace App\Physician\Domain;

class GetOnePhysicianUseCase
{
    private PhysicianRepositoryInterface $repository;

    public function __construct(PhysicianRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Physician
    {
        return $this->repository->getOne($id);
    }
}