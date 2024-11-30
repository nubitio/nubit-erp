<?php

namespace App\Transfer\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface TransferRepositoryInterface
{
    /**
     * @return PaginatorInterface<Transfer>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Transfer;

    public function insert(Transfer $transfer): Transfer;

    public function update(Transfer $transfer): Transfer;

    public function delete(string $id): void;
}
