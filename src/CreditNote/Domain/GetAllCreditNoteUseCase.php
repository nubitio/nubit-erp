<?php

namespace App\CreditNote\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllCreditNoteUseCase
{
    public function __construct(
        private CreditNoteRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<CreditNote>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
