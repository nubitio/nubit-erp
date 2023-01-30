<?php

namespace App\Auth\Domain;

use App\User\Domain\User;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class AppAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const AUTH_ROUTE = '_api_/auth_post';

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === self::AUTH_ROUTE && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent());
        $username = $data->username;
        $password = $data->password;

        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password),
            [
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        /** @var User $user */
        $user = $token->getUser();
        $session->set('user_id', $user->usuario_id);
        $session->set('username', $user->username);
        $session->set('role_id', $user->rol->rol_id);

        $key = $_ENV['APP_SECRET'];

        $tokenData = [
            'id' => $user->usuario_id,
            'username' => $user->username,
            'roles' => $user->getRoles(),
            'iat' => time(),
            'exp' => time() + 3600,
            'sub' => $user->usuario_id,
            'name' => $user->username,
            'role' => $user->rol->rol_id,
        ];

        $token = JWT::encode($tokenData, $key);

        return new JsonResponse(['token' => $token]);
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
