<?php

namespace App\ComboOption\Domain;

class GetOneComboOptionUseCase
{
    private ComboOptionRepositoryInterface $repository;

    public function __construct(ComboOptionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?ComboOption
    {
        return $this->repository->getOne($id);
    }
}
