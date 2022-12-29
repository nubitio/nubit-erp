<?php

namespace App\Local\Domain;

class LocalDeleteUseCase
{
    private LocalRepositoryInterface $repository;

    public function __construct(LocalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}
