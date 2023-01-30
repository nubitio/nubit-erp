<?php

namespace App\Auth\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Auth\Domain\GetTokenUseCase;
use App\Auth\Domain\Token;
use App\User\Domain\GetOneUserByUsernameUseCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

readonly class AuthProcessor implements ProcessorInterface
{
    public function __construct(
        private GetOneUserByUsernameUseCase $getOneUserByUsernameUseCase,
        private GetTokenUseCase             $getTokenUseCase
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Token
    {
        $user = $this->getOneUserByUsernameUseCase->__invoke($data->username);

        if (!$user && !$user?->estado) {
            throw new UserNotFoundException('User not found');
        }

        if (!password_verify($data->password, $user->getPassword())) {
            throw new AuthenticationException('Invalid credentials');
        }

        return $this->getTokenUseCase->__invoke($user);
    }
}