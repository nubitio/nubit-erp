<?php

namespace App\Mesa\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface MesaRepositoryInterface
{
    /**
     * @return PaginatorInterface<Mesa>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Mesa;

    public function insert(Mesa $mesa): Mesa;

    public function update(Mesa $mesa): Mesa;

    public function delete(int $id): void;
}
