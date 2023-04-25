<?php

namespace App\MilitaryPatient\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface MilitaryPatientRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<MilitaryPatient>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?MilitaryPatient;

    public function insert(MilitaryPatient $militaryPatient): MilitaryPatient;

    public function update(MilitaryPatient $militaryPatient): MilitaryPatient;

    public function delete(int $id): void;
}