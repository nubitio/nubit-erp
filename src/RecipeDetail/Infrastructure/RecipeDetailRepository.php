<?php

namespace App\RecipeDetail\Infrastructure;

use App\RecipeDetail\Domain\RecipeDetailRepositoryInterface;
use App\RecipeDetail\Domain\RecipeDetail;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class RecipeDetailRepository implements RecipeDetailRepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}