<?php

namespace App\Local\Domain;

class LocalUpdateUseCase
{
    private LocalRepositoryInterface $repository;

    public function __construct(LocalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Local $local): void
    {
        $this->repository->update($local);
    }
}
