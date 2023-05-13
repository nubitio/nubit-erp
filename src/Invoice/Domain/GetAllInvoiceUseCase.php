<?php

namespace App\Invoice\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllInvoiceUseCase
{
    public function __construct(
        private InvoiceRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Invoice>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
