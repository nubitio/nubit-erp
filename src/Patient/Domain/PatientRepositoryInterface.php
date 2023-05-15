<?php

namespace App\Patient\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface PatientRepositoryInterface
{
    /**
     * @return PaginatorInterface<Patient>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Patient;

    public function insert(Patient $patient): Patient;

    public function update(Patient $patient): Patient;

    public function delete(int $id): void;
}
