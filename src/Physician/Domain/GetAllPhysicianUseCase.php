<?php

namespace App\Physician\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPhysicianUseCase
{
    public function __construct(
        private PhysicianRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Physician>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
