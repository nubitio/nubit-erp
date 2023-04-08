<?php

namespace App\Position\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface PositionRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Position>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Position;

    public function insert(Position $position): Position;

    public function update(Position $position): Position;

    public function delete(int $id): void;
}