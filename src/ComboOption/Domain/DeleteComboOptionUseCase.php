<?php

namespace App\ComboOption\Domain;

class DeleteComboOptionUseCase
{
    private ComboOptionRepositoryInterface $repository;

    public function __construct(ComboOptionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
