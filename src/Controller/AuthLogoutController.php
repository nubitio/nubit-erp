<?php

namespace App\Controller;

use App\Core\Security\Auth\JWTAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthLogoutController extends AbstractController
{
    #[Route('/api/v1/auth/logout', name: 'auth_logout', methods: ['POST'])]
    public function __invoke(): Response
    {
        $response = new JsonResponse(['message' => 'ok']);
        $expiredAccessCookie = Cookie::create(
            JWTAuthenticator::AUTH_COOKIE,
            '',
            (new \DateTimeImmutable('-1 hour'))->getTimestamp(),
            '/',
            null,
            true
        );
        $expiredRefreshCookie = Cookie::create(
            JWTAuthenticator::REFRESH_COOKIE,
            '',
            (new \DateTimeImmutable('-1 hour'))->getTimestamp(),
            '/',
            null,
            true
        );
        $response->headers->setCookie($expiredAccessCookie);
        $response->headers->setCookie($expiredRefreshCookie);

        return $response;
    }
}
