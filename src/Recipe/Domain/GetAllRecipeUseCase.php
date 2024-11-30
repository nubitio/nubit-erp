<?php

namespace App\Recipe\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllRecipeUseCase
{
    public function __construct(
        private RecipeRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Recipe>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
