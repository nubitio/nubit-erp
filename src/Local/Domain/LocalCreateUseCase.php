<?php

namespace App\Local\Domain;

class LocalCreateUseCase
{
    private LocalRepositoryInterface $repository;

    public function __construct(LocalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Local $local): void
    {
        $this->repository->insert($local);
    }
}