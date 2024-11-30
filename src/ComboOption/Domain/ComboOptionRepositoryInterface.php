<?php

namespace App\ComboOption\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface ComboOptionRepositoryInterface
{
    /**
     * @return PaginatorInterface<ComboOption>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?ComboOption;

    public function insert(ComboOption $comboOption): ComboOption;

    public function update(ComboOption $comboOption): ComboOption;

    public function delete(int $id): void;
}
