<?php

namespace App\Config\Domain;

class ConfigDeleteUseCase
{
    public function __construct(private readonly ConfigRepositoryInterface $repository)
    {
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
