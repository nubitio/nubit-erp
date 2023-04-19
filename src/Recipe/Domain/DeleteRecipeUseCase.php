<?php

namespace App\Recipe\Domain;

class DeleteRecipeUseCase
{
    private RecipeRepositoryInterface $repository;

    public function __construct(RecipeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}