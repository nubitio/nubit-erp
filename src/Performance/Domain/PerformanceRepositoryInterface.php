<?php

namespace App\Performance\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface PerformanceRepositoryInterface
{
    /**
     * @return PaginatorInterface<Performance>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Performance;

    public function insert(Performance $performance): Performance;

    public function update(Performance $performance): Performance;

    public function delete(int $id): void;
}
