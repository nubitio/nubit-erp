<?php

namespace App\PatientType\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPatientTypeUseCase
{
    public function __construct(
        private PatientTypeRepositoryInterface $repository
    )
    {
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<PatientType>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}