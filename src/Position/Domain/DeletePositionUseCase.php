<?php

namespace App\Position\Domain;

class DeletePositionUseCase
{
    private PositionRepositoryInterface $repository;

    public function __construct(PositionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
