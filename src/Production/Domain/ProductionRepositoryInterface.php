<?php

namespace App\Production\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface ProductionRepositoryInterface
{
    /**
     * @return PaginatorInterface<Production>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Production;

    public function insert(Production $production): Production;

    public function update(Production $production): Production;

    public function delete(int $id): void;
}
