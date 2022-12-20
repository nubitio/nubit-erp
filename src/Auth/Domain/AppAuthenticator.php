<?php

namespace App\Auth\Domain;

use App\User\Domain\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    public const LOGIN_ROUTE = 'login_index';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    )
    {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'login'
            && $request->isMethod('POST');
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
                new RememberMeBadge()
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $session = $request->getSession();
        /** @var User $user */
        $user = $token->getUser();
        $session->set('usuario_id', $user->usuario_id);
        $session->set('usuario', $user->username);
        $session->set('rol_id', $user->rol_id);

        return new JsonResponse([
            'success' => true,
            'message' => 'Login successful',
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate(self::LOGIN_ROUTE));
    }
}