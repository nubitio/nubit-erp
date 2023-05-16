<?php

namespace App\Consultation\Domain;

class CreateConsultationUseCase
{
    private ConsultationRepositoryInterface $repository;

    public function __construct(ConsultationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Consultation $consultation): Consultation
    {
        return $this->repository->insert($consultation);
    }
}
