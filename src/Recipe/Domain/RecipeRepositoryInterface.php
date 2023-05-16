<?php

namespace App\Recipe\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface RecipeRepositoryInterface
{
    /**
     * @return PaginatorInterface<Recipe>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Recipe;

    public function insert(Recipe $recipe): Recipe;

    public function update(Recipe $recipe): Recipe;

    public function delete(int $id): void;
}
