<?php

namespace App\RecipeDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface RecipeDetailRepositoryInterface
{
    /**
     * @return PaginatorInterface<RecipeDetail>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;
}
