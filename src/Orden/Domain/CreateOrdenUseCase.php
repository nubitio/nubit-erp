<?php

namespace App\Orden\Domain;

class CreateOrdenUseCase
{
    private OrdenRepositoryInterface $repository;

    public function __construct(OrdenRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Orden $orden): Orden
    {
        return $this->repository->insert($orden);
    }
}
