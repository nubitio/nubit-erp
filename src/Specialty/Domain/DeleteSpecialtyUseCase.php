<?php

namespace App\Specialty\Domain;

class DeleteSpecialtyUseCase
{
    private SpecialtyRepositoryInterface $repository;

    public function __construct(SpecialtyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}