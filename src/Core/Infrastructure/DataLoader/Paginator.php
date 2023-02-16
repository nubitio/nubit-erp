<?php

namespace App\Core\Infrastructure\DataLoader;

use ApiPlatform\State\Pagination\PaginatorInterface as ApiPlatformPaginatorInterface;
use Traversable;

readonly class Paginator implements PaginatorInterface
{
    /**
     * @template T
     *
     * @param array<T> $items
     */
    public function __construct(
        public array                         $items,
        public ApiPlatformPaginatorInterface $paginator
    )
    {
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getLastPage(): float
    {
        return floor($this->paginator->getTotalItems() / $this->paginator->getItemsPerPage());
    }

    public function getTotalItems(): float
    {
        return $this->paginator->getTotalItems();
    }

    public function getCurrentPage(): float
    {
        return $this->paginator->getCurrentPage();
    }

    public function getItemsPerPage(): float
    {
        return $this->paginator->getItemsPerPage();
    }
}