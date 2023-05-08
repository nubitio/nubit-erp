<?php

namespace App\PatientType\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface PatientTypeRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<PatientType>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?PatientType;

    public function insert(PatientType $patientType): PatientType;

    public function update(PatientType $patientType): PatientType;

    public function delete(int $id): void;
}