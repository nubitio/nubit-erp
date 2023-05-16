<?php

namespace App\Consultation\Domain;

class DeleteConsultationUseCase
{
    private ConsultationRepositoryInterface $repository;

    public function __construct(ConsultationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
