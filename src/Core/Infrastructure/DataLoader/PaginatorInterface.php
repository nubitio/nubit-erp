<?php

namespace App\Core\Infrastructure\DataLoader;

use ApiPlatform\State\Pagination\PaginatorInterface as ApiPlatformPaginatorInterface;

/**
 * @template T of object
 *
 * @extends ApiPlatformPaginatorInterface<T>
 */
interface PaginatorInterface extends \IteratorAggregate, ApiPlatformPaginatorInterface
{
}