<?php

namespace App\Company\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CompanyRepositoryInterface
{
    /**
     * @return PaginatorInterface<Company>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Company;

    public function insert(Company $company): Company;

    public function update(Company $company): Company;

    public function delete(string $id): void;
}
