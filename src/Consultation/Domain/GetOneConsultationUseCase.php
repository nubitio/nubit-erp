<?php

namespace App\Consultation\Domain;

class GetOneConsultationUseCase
{
    private ConsultationRepositoryInterface $repository;

    public function __construct(ConsultationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Consultation
    {
        return $this->repository->getOne($id);
    }
}