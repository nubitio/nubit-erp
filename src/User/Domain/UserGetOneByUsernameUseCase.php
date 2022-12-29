<?php

namespace App\User\Domain;

class UserGetOneByUsernameUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(string $username): ?User
    {
        return $this->userRepository->getOneByUsername($username);
    }
}
