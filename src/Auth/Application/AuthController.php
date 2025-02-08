<?php

namespace App\Auth\Application;

use App\Base\BaseController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends BaseController
{
    #[Route('/auth/login', name: 'login', methods: ['POST'])]
    public function login(): Response
    {
        throw new \Exception('This should never be reached');
    }

    #[Route('/auth/logout', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        throw new \Exception('This should never be reached!');
    }
}
