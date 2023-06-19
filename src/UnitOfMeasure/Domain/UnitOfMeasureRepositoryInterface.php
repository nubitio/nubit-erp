<?php

namespace App\UnitOfMeasure\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface UnitOfMeasureRepositoryInterface
{
    /**
     * @return PaginatorInterface<UnitOfMeasure>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?UnitOfMeasure;

    public function insert(UnitOfMeasure $unitOfMeasure): UnitOfMeasure;

    public function update(UnitOfMeasure $unitOfMeasure): UnitOfMeasure;

    public function delete(int $id): void;
}
