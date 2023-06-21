<?php

namespace App\Auth\Api;

use ApiPlatform\Metadata\Operation;
use App\Auth\Domain\GetTokenUseCase;
use App\Auth\Domain\Token;
use App\Core\Domain\Api\ApiProcessorInterface;
use App\User\Domain\GetOneUserByUsernameUseCase;
use App\User\Domain\User;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * @implements ApiProcessorInterface<User>
 */
readonly class AuthProcessor implements ApiProcessorInterface
{
    public function __construct(
        private GetOneUserByUsernameUseCase $getOneUserByUsernameUseCase,
        private GetTokenUseCase $getTokenUseCase
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Token
    {
        $user = $this->getOneUserByUsernameUseCase->__invoke(strval($data->username));

        if (!$user || !$user->estado) {
            throw new UserNotFoundException('User not found');
        }

        if (!password_verify(strval($data->password), strval($user->getPassword()))) {
            throw new AuthenticationException('Invalid credentials');
        }

        return $this->getTokenUseCase->__invoke($user);
    }
}
