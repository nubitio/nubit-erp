<?php

namespace App\{{module}}\Infrastructure;

use App\{{module}}\Domain\{{module}}RepositoryInterface;
use App\{{module}}\Domain\{{module}};
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

readonly class {{module}}Repository implements {{module}}RepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(Options $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}