<?php

namespace App\OrdenDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllOrdenDetailUseCase
{
    public function __construct(
        private OrdenDetailRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<OrdenDetail>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
