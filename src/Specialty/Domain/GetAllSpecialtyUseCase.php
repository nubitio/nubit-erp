<?php

namespace App\Specialty\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllSpecialtyUseCase
{
    public function __construct(
        private SpecialtyRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Specialty>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
