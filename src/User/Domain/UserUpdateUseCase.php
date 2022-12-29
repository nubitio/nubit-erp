<?php

namespace App\User\Domain;

class UserUpdateUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(User $user): void
    {
        $this->repository->update($user);
    }
}
