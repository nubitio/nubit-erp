<?php

namespace App\Mesa\Domain;

class UpdateMesaUseCase
{
    private MesaRepositoryInterface $repository;

    public function __construct(MesaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Mesa $mesa): Mesa
    {
        return $this->repository->update($mesa);
    }
}
