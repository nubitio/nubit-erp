<?php

namespace App\Company\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface CompanyRepositoryInterface
{
    /**
     * @return PaginatorInterface<Company>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Company;

    public function insert(Company $company): Company;

    public function update(Company $company): Company;

    public function delete(string $id): void;
}
