<?php

namespace App\UnitOfMeasure\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface UnitOfMeasureRepositoryInterface
{
    /**
     * @return PaginatorInterface<UnitOfMeasure>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?UnitOfMeasure;

    public function insert(UnitOfMeasure $unitOfMeasure): UnitOfMeasure;

    public function update(UnitOfMeasure $unitOfMeasure): UnitOfMeasure;

    public function delete(int $id): void;
}
