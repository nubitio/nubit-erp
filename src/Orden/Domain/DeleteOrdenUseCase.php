<?php

namespace App\Orden\Domain;

class DeleteOrdenUseCase
{
    private OrdenRepositoryInterface $repository;

    public function __construct(OrdenRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
