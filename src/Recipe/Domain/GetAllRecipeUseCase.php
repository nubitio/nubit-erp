<?php

namespace App\Recipe\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllRecipeUseCase
{
    public function __construct(
        private RecipeRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Recipe>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
