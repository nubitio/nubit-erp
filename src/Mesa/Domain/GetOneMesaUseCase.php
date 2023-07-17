<?php

namespace App\Mesa\Domain;

class GetOneMesaUseCase
{
    private MesaRepositoryInterface $repository;

    public function __construct(MesaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Mesa
    {
        return $this->repository->getOne($id);
    }
}
