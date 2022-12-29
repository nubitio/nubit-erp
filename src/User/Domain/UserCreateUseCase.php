<?php

namespace App\User\Domain;

class UserCreateUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(User $user): void
    {
        $this->repository->insert($user);
    }
}
