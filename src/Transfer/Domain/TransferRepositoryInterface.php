<?php

namespace App\Transfer\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface TransferRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Transfer>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Transfer;

    public function insert(Transfer $transfer): Transfer;

    public function update(Transfer $transfer): Transfer;

    public function delete(string $id): void;
}