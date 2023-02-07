<?php

namespace App\Auth\Domain;

use App\User\Domain\User;
use Firebase\JWT\JWT;

readonly class GetTokenUseCase
{
    public function __invoke(User $user, $branchId = null): Token
    {
        $key = $_ENV['APP_SECRET'];

        $tokenData = [
            'id'       => $user->usuario_id,
            'username' => $user->username,
            'roles'    => $user->getRoles(),
            'iat'      => time(),
            'exp'      => time() + 3600 * 24 * 7,
            'sub'      => $user->usuario_id,
            'name'     => $user->username,
            'role'     => $user->rol?->rol_id,
            'branch'   => $branchId,
        ];

        $token = JWT::encode($tokenData, $key);

        return new Token($token);
    }
}