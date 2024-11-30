<?php

namespace App\Recipe\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface RecipeRepositoryInterface
{
    /**
     * @return PaginatorInterface<Recipe>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?Recipe;

    public function insert(Recipe $recipe): Recipe;

    public function update(Recipe $recipe): Recipe;

    public function delete(int $id): void;
}
