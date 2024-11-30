<?php

namespace App\Production\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface ProductionRepositoryInterface
{
    /**
     * @return PaginatorInterface<Production>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?Production;

    public function insert(Production $production): Production;

    public function update(Production $production): Production;

    public function delete(int $id): void;
}
