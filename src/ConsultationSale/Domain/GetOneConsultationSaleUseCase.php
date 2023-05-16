<?php

namespace App\ConsultationSale\Domain;

class GetOneConsultationSaleUseCase
{
    private ConsultationSaleRepositoryInterface $repository;

    public function __construct(ConsultationSaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?ConsultationSale
    {
        return $this->repository->getOne($id);
    }
}
