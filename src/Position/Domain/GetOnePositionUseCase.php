<?php

namespace App\Position\Domain;

class GetOnePositionUseCase
{
    private PositionRepositoryInterface $repository;

    public function __construct(PositionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Position
    {
        return $this->repository->getOne($id);
    }
}
