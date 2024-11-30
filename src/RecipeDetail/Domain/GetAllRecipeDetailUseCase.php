<?php

namespace App\RecipeDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllRecipeDetailUseCase
{
    public function __construct(
        private RecipeDetailRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<RecipeDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
