<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface {{module}}RepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<{{module}}>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne({{entityIdParam}}): ?{{module}};

    public function insert({{module}} ${{camelModule}}): {{module}};

    public function update({{module}} ${{camelModule}}): {{module}};

    public function delete({{entityIdParam}}): void;
}