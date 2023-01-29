<?php

namespace App\User\Domain;

class CreateUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(User $user): User
    {
        return $this->repository->insert($user);
    }
}