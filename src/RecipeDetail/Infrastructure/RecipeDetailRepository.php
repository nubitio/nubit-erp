<?php

namespace App\RecipeDetail\Infrastructure;

use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\RecipeDetail\Domain\RecipeDetailRepositoryInterface;

readonly class RecipeDetailRepository implements RecipeDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    ) {
    }

    /** {@inheritdoc} */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}
