<?php

namespace App\{{module}}\Domain;

interface {{module}}RepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert({{module}} ${{camelModule}}): void;

    public function update({{module}} ${{camelModule}}): void;

    public function delete(string $id): void;
}