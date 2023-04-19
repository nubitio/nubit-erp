<?php

namespace App\RecipeDetail\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface RecipeDetailRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<RecipeDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}