<?php

namespace App\User\Domain;

class GetOneUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?User
    {
        return $this->repository->getOne($id);
    }
}
