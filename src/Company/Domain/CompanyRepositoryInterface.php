<?php

namespace App\Company\Domain;

interface CompanyRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Company $company): void;

    public function update(Company $company): void;

    public function delete(string $id): void;
}