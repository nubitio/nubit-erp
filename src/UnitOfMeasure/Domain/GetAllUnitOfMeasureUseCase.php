<?php

namespace App\UnitOfMeasure\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllUnitOfMeasureUseCase
{
    public function __construct(
        private UnitOfMeasureRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<UnitOfMeasure>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
