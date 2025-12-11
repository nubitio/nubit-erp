<?php

namespace App\Controller;

use App\Entity\RefreshToken;
use DateTimeImmutable;
use App\Core\Security\Auth\JWTAuthenticator;
use App\Repository\RefreshTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

class AuthLogoutController extends AbstractController
{
    public function __construct(
        private readonly RefreshTokenRepository $refreshTokenRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $refreshToken = $request->cookies->get(JWTAuthenticator::REFRESH_COOKIE);
        if ($refreshToken) {
            $storedToken = $this->refreshTokenRepository->findActiveByHash(hash('sha256', $refreshToken));
            if ($storedToken instanceof RefreshToken) {
                $storedToken->revoke(new DateTimeImmutable());
                $this->entityManager->flush();
            }
        }

        $response = new JsonResponse(['message' => 'ok']);
        $expiredAccessCookie = Cookie::create(
            JWTAuthenticator::AUTH_COOKIE,
            '',
            new DateTimeImmutable('-1 hour')->getTimestamp(),
            '/',
            null,
            true
        );
        $expiredRefreshCookie = Cookie::create(
            JWTAuthenticator::REFRESH_COOKIE,
            '',
            new DateTimeImmutable('-1 hour')->getTimestamp(),
            '/',
            null,
            true
        );
        $response->headers->setCookie($expiredAccessCookie);
        $response->headers->setCookie($expiredRefreshCookie);

        return $response;
    }
}
