<?php

namespace App\User\Domain;

readonly class GetOneUserByUsernameUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function __invoke(string $username): ?User
    {
        return $this->userRepository->getOneByUsername($username);
    }
}
