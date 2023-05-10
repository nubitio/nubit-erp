<?php

namespace App\ComboOption\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ComboOptionRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<ComboOption>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?ComboOption;

    public function insert(ComboOption $comboOption): ComboOption;

    public function update(ComboOption $comboOption): ComboOption;

    public function delete(int $id): void;
}