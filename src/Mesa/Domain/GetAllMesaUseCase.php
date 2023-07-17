<?php

namespace App\Mesa\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllMesaUseCase
{
    public function __construct(
        private MesaRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Mesa>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
