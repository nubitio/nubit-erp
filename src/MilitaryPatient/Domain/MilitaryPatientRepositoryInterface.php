<?php

namespace App\MilitaryPatient\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Sale\Domain\InvoiceExtraData;

interface MilitaryPatientRepositoryInterface
{
    /**
     * @return PaginatorInterface<MilitaryPatient>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?MilitaryPatient;

    public function insert(MilitaryPatient $militaryPatient): MilitaryPatient;

    public function update(MilitaryPatient $militaryPatient): MilitaryPatient;

    public function delete(int $id): void;

    public function getInvoiceExtraDataByCustomerId(string $customerId, string $saleId): ?InvoiceExtraData;
}
