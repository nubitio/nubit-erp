<?php

namespace App\Physician\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface PhysicianRepositoryInterface
{
    /**
     * @return PaginatorInterface<Physician>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Physician;

    public function insert(Physician $physician): Physician;

    public function update(Physician $physician): Physician;

    public function delete(int $id): void;
}
