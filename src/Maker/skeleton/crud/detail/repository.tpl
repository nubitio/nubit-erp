<?php

namespace App\{{module}}\Infrastructure;

use App\{{module}}\Domain\{{module}}RepositoryInterface;
use App\{{module}}\Domain\{{module}};
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class {{module}}Repository implements {{module}}RepositoryInterface
{
    public function __construct(
        private DataLoader $dataLoader,
    )
    {
    }

    /** @inheritdoc */
    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->load($options);
    }
}
