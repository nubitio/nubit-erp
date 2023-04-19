<?php

namespace App\Recipe\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface RecipeRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Recipe>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Recipe;

    public function insert(Recipe $recipe): Recipe;

    public function update(Recipe $recipe): Recipe;

    public function delete(int $id): void;
}