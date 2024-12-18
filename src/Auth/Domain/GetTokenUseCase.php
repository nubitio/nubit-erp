<?php

namespace App\Auth\Domain;

use App\Branch\Infrastructure\BranchEntity;
use App\User\Domain\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;

readonly class GetTokenUseCase
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(User $user, ?string $branchId = null): Token
    {
        $key = $_ENV['APP_SECRET'];

        $branch = null;

        if (null != $branchId) {
            $branch = $this->entityManager->find(BranchEntity::class, $branchId);
        }

        $tokenData = [
            'sub' => $user->usuario_id,
            'id' => $user->usuario_id,
            'username' => $user->username,
            'name' => $user->username,
            'role' => $user->rol?->rol_id,
            'roles' => $user->getRoles(),
            'branchId' => $branchId,
            'branch' => $branch?->descripcion,
            'iat' => time(),
            'exp' => time() + 3600 * 24 * 7,
        ];

        $token = JWT::encode($tokenData, $key, 'HS256');

        return new Token($token);
    }
}
