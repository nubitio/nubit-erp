<?php

namespace App\Orden\Domain;

class UpdateOrdenUseCase
{
    private OrdenRepositoryInterface $repository;

    public function __construct(OrdenRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Orden $orden): Orden
    {
        return $this->repository->update($orden);
    }
}
