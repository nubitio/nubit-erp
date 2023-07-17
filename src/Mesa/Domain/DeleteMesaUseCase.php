<?php

namespace App\Mesa\Domain;

class DeleteMesaUseCase
{
    private MesaRepositoryInterface $repository;

    public function __construct(MesaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
