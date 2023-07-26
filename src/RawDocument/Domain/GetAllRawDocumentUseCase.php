<?php

namespace App\RawDocument\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllRawDocumentUseCase
{
    public function __construct(
        private RawDocumentRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<RawDocument>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
