<?php

namespace App\Ambiente\Domain;

class GetOneAmbienteUseCase
{
    private AmbienteRepositoryInterface $repository;

    public function __construct(AmbienteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Ambiente
    {
        return $this->repository->getOne($id);
    }
}
