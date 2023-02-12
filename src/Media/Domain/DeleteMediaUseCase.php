<?php

namespace App\Media\Domain;

class DeleteMediaUseCase
{
    private MediaRepositoryInterface $repository;

    public function __construct(MediaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}