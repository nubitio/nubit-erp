<?php

namespace App\Core\Infrastructure\DataLoader\Paginator;

use ApiPlatform\State\Pagination\PaginatorInterface as ApiPlatformPaginatorInterface;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

/**
 * @template T of object
 */
readonly class Paginator implements PaginatorInterface
{
    /**
     * @param array<T>                         $items
     * @param ApiPlatformPaginatorInterface<T> $paginator
     */
    public function __construct(
        public array $items,
        public ApiPlatformPaginatorInterface $paginator,
    ) {
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->items);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->items);
    }

    #[\Override]
    public function getLastPage(): float
    {
        return floor($this->paginator->getTotalItems() / $this->paginator->getItemsPerPage());
    }

    #[\Override]
    public function getTotalItems(): float
    {
        return $this->paginator->getTotalItems();
    }

    #[\Override]
    public function getCurrentPage(): float
    {
        return $this->paginator->getCurrentPage();
    }

    #[\Override]
    public function getItemsPerPage(): float
    {
        return $this->paginator->getItemsPerPage();
    }
}
