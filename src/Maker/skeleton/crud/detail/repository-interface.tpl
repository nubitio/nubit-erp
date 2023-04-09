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
}