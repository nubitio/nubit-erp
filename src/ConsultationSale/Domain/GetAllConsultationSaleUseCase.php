<?php

namespace App\ConsultationSale\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllConsultationSaleUseCase
{
    public function __construct(
        private ConsultationSaleRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<ConsultationSale>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
