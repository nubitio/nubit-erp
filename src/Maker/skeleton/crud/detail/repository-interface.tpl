<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface {{module}}RepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;
}