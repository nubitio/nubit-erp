<?php

namespace App\Config\Domain;

interface ConfigRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Config $config): void;

    public function update(Config $config): void;

    public function delete(string $id): void;
}
