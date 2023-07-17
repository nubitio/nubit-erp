<?php

namespace App\Orden\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface OrdenRepositoryInterface
{
    /**
     * @return PaginatorInterface<Orden>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Orden;

    public function insert(Orden $orden): Orden;

    public function update(Orden $orden): Orden;

    public function delete(int $id): void;
}
