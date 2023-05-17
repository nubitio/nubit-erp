<?php

namespace App\Recipe\Domain;

class CreateRecipeUseCase
{
    private RecipeRepositoryInterface $repository;

    public function __construct(RecipeRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Recipe $recipe): Recipe
    {
        return $this->repository->insert($recipe);
    }
}
