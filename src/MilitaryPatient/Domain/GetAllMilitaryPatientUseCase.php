<?php

namespace App\MilitaryPatient\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllMilitaryPatientUseCase
{
    public function __construct(
        private MilitaryPatientRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<MilitaryPatient>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
