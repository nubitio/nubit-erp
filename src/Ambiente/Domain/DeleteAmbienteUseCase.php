<?php

namespace App\Ambiente\Domain;

class DeleteAmbienteUseCase
{
    private AmbienteRepositoryInterface $repository;

    public function __construct(AmbienteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
