<?php

namespace App\SaleDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

interface SaleDetailRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;
}