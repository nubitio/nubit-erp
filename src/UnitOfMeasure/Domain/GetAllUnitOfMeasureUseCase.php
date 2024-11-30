<?php

namespace App\UnitOfMeasure\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllUnitOfMeasureUseCase
{
    public function __construct(
        private UnitOfMeasureRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<UnitOfMeasure>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
