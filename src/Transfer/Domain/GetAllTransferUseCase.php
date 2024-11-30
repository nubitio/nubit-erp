<?php

namespace App\Transfer\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllTransferUseCase
{
    public function __construct(
        private TransferRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Transfer>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
