<?php

namespace App\User\Domain;

class UpdateUserUseCase
{
    private UserRepositoryInterface $repository;

    public function __construct(UserRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(User $user): User
    {
        return $this->repository->update($user);
    }
}
