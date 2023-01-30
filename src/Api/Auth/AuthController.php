<?php

namespace App\Api\Auth;

use App\Base\BaseController;
use App\User\Domain\GetOneUserByUsernameUseCase;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends BaseController
{

    #[Route('/api/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(Request $request, GetOneUserByUsernameUseCase $userGetOneByUsername): JsonResponse
    {
        $key = $_ENV['APP_SECRET'];

        $data = json_decode($request->getContent(), true);
        $username = $data['username'];
        $password = $data['password'];

        $user = $userGetOneByUsername($username);

        if (!$user) {
            return $this->json(['message' => 'User not found'], 404);
        }

        if ($user->estado != 1) {
            return $this->json(['message' => 'User not active'], 401);
        }

        if (!password_verify($password, $user->getPassword())) {
            return $this->json(['message' => 'Invalid password'], 401);
        }

        $tokenData = [
            'id'       => $user->usuario_id,
            'username' => $user->username,
            'roles'    => $user->getRoles(),
            'iat'      => time(),
            'exp'      => time() + 3600,
            'sub'      => $user->usuario_id,
            'name'     => $user->username,
            'role'     => $user->rol_id,
        ];

        $token = JWT::encode($tokenData, $key);

        return $this->json(['token' => $token]);
    }
}