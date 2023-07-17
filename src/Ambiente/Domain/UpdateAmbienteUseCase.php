<?php

namespace App\Ambiente\Domain;

class UpdateAmbienteUseCase
{
    private AmbienteRepositoryInterface $repository;

    public function __construct(AmbienteRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Ambiente $ambiente): Ambiente
    {
        return $this->repository->update($ambiente);
    }
}
