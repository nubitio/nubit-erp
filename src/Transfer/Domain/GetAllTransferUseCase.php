<?php

namespace App\Transfer\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllTransferUseCase
{
    public function __construct(
        private TransferRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Transfer>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
