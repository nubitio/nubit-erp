<?php

namespace App\Specialty\Domain;

class CreateSpecialtyUseCase
{
    private SpecialtyRepositoryInterface $repository;

    public function __construct(SpecialtyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Specialty $specialty): Specialty
    {
        return $this->repository->insert($specialty);
    }
}