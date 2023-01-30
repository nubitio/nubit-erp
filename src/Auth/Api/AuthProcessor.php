<?php

namespace App\Auth\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\User\Domain\GetOneUserByUsernameUseCase;
use Firebase\JWT\JWT;

readonly class AuthProcessor implements ProcessorInterface
{
    public function __construct(
        public GetOneUserByUsernameUseCase $getOneUserByUsernameUseCase,
    )
    {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?TokenDto
    {
        $key = $_ENV['APP_SECRET'];

        $username = $data->username;
        $password = $data->password;

        $user = $this->getOneUserByUsernameUseCase->__invoke($username);

        if (!$user) {
            throw new \Exception('User not found');
        }

        if ($user->estado != 1) {
            throw new \Exception('User is inactive');
        }

        if (!password_verify($password, $user->getPassword())) {
            throw new \Exception('Invalid password');
        }

        $tokenData = [
            'id'       => $user->usuario_id,
            'username' => $user->username,
            'roles'    => $user->getRoles(),
            'iat'      => time(),
            'exp'      => time() + 3600,
            'sub'      => $user->usuario_id,
            'name'     => $user->username,
        ];

        $token = JWT::encode($tokenData, $key);

        $dto = new TokenDto();
        $dto->token = $token;

        return $dto;
    }
}