<?php

namespace App\Core\Infrastructure\DataLoader\Paginator;

use ApiPlatform\State\Pagination\PaginatorInterface as ApiPlatformPaginatorInterface;

interface PaginatorInterface extends \IteratorAggregate, ApiPlatformPaginatorInterface
{
}
