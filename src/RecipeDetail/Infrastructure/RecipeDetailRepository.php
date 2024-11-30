<?php

namespace App\RecipeDetail\Infrastructure;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\RecipeDetail\Domain\RecipeDetailRepositoryInterface;

readonly class RecipeDetailRepository implements RecipeDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }
}
