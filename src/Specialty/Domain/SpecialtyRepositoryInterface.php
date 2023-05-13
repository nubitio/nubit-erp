<?php

namespace App\Specialty\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface SpecialtyRepositoryInterface
{
    /**
     * @return PaginatorInterface<Specialty>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Specialty;

    public function insert(Specialty $specialty): Specialty;

    public function update(Specialty $specialty): Specialty;

    public function delete(int $id): void;
}
