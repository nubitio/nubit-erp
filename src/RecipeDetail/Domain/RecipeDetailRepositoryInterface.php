<?php

namespace App\RecipeDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface RecipeDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<RecipeDetail>
     */
    public function getAll(Options $options): PaginatorInterface;
}
