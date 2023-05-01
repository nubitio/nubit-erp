<?php

namespace App\ConsultationSale\Domain;

class UpdateConsultationSaleUseCase
{
    private ConsultationSaleRepositoryInterface $repository;

    public function __construct(ConsultationSaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(ConsultationSale $consultationSale): ConsultationSale
    {
        return $this->repository->update($consultationSale);
    }
}