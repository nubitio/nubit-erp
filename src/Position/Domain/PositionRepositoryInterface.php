<?php

namespace App\Position\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface PositionRepositoryInterface
{
    /**
     * @return PaginatorInterface<Position>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?Position;

    public function insert(Position $position): Position;

    public function update(Position $position): Position;

    public function delete(int $id): void;
}
