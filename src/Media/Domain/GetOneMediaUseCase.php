<?php

namespace App\Media\Domain;

class GetOneMediaUseCase
{
    private MediaRepositoryInterface $repository;

    public function __construct(MediaRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Media
    {
        return $this->repository->getOne($id);
    }
}