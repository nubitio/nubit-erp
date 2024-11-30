<?php

namespace App\ComboOption\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllComboOptionUseCase
{
    public function __construct(
        private ComboOptionRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<ComboOption>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
