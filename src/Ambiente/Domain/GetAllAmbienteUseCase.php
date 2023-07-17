<?php

namespace App\Ambiente\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllAmbienteUseCase
{
    public function __construct(
        private AmbienteRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Ambiente>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
