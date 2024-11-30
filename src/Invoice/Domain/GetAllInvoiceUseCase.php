<?php

namespace App\Invoice\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Invoice>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
