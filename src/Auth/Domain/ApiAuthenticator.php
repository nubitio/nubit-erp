<?php

namespace App\Auth\Domain;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class ApiAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const AUTH_HEADER = 'Authorization';

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::AUTH_HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get(self::AUTH_HEADER);

        if (null === $apiToken) {
            throw new AuthenticationException('No API token provided', Response::HTTP_UNAUTHORIZED);
        }

        $apiToken = str_replace('Bearer ', '', $apiToken);

        $tokenData = JWT::decode($apiToken, $_ENV['APP_SECRET'], ['HS256']);

        $username = $tokenData->username;

        return new SelfValidatingPassport(
            new UserBadge($username),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'message' => 'Authentication Required',
        ], Response::HTTP_UNAUTHORIZED);
    }
}
