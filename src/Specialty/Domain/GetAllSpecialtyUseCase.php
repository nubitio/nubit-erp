<?php

namespace App\Specialty\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllSpecialtyUseCase
{
    public function __construct(
        private SpecialtyRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Specialty>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
