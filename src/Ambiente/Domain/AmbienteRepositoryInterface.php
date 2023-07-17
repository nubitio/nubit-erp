<?php

namespace App\Ambiente\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface AmbienteRepositoryInterface
{
    /**
     * @return PaginatorInterface<Ambiente>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Ambiente;

    public function insert(Ambiente $ambiente): Ambiente;

    public function update(Ambiente $ambiente): Ambiente;

    public function delete(int $id): void;
}
