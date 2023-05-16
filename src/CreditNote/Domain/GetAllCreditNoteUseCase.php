<?php

namespace App\CreditNote\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCreditNoteUseCase
{
    public function __construct(
        private CreditNoteRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<CreditNote>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
