<?php

namespace App\Orden\Domain;

class GetOneOrdenUseCase
{
    private OrdenRepositoryInterface $repository;

    public function __construct(OrdenRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Orden
    {
        return $this->repository->getOne($id);
    }
}
