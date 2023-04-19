<?php

namespace App\Recipe\Domain;

class GetOneRecipeUseCase
{
    private RecipeRepositoryInterface $repository;

    public function __construct(RecipeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Recipe
    {
        return $this->repository->getOne($id);
    }
}