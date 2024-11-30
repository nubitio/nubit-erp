<?php

namespace App\RawDocument\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllRawDocumentUseCase
{
    public function __construct(
        private RawDocumentRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<RawDocument>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
