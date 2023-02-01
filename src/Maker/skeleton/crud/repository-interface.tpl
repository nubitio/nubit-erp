<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface {{module}}RepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?{{module}};

    public function insert({{module}} ${{camelModule}}): {{module}};

    public function update({{module}} ${{camelModule}}): {{module}};

    public function delete(string $id): void;
}