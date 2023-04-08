<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAll{{module}}UseCase
{
    public function __construct(
        private {{module}}RepositoryInterface $repository
    )
    {
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<{{module}}>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}