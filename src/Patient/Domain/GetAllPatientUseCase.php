<?php

namespace App\Patient\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPatientUseCase
{
    public function __construct(
        private PatientRepositoryInterface $repository
    )
    {
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<Patient>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}