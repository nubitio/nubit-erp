<?php

namespace App\Core\Domain\DataLoader\Paginator;

use ApiPlatform\State\Pagination\PaginatorInterface as ApiPlatformPaginatorInterface;

interface PaginatorInterface extends \IteratorAggregate, ApiPlatformPaginatorInterface
{
}
