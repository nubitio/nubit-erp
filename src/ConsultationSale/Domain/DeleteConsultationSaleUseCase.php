<?php

namespace App\ConsultationSale\Domain;

class DeleteConsultationSaleUseCase
{
    private ConsultationSaleRepositoryInterface $repository;

    public function __construct(ConsultationSaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}