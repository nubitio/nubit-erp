<?php

namespace App\Specialty\Domain;

class GetOneSpecialtyUseCase
{
    private SpecialtyRepositoryInterface $repository;

    public function __construct(SpecialtyRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Specialty
    {
        return $this->repository->getOne($id);
    }
}
