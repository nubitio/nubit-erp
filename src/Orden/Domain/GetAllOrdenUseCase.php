<?php

namespace App\Orden\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllOrdenUseCase
{
    public function __construct(
        private OrdenRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Orden>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
