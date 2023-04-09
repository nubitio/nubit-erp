<?php

namespace App\Position\Domain;

class CreatePositionUseCase
{
    private PositionRepositoryInterface $repository;

    public function __construct(PositionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Position $position): Position
    {
        return $this->repository->insert($position);
    }
}