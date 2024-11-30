<?php

namespace App\Auth\Domain;

use App\Core\Domain\Common\ApiResponse;
use App\User\Domain\User;
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
    public function __construct(
        private readonly GetTokenUseCase $getTokenUseCase,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return 'login' === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        /** @var array<string, string> $data */
        $data = json_decode($request->getContent(), true);
        $username = $data['username'];
        $password = $data['password'];

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
        /** @var User $user */
        $user = $token->getUser();

        $token = $this->getTokenUseCase->__invoke($user);

        return new JsonResponse(new ApiResponse(true, 'Login success', $token));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(
            new ApiResponse(
                false,
                $exception->getMessage()
            ),
            Response::HTTP_UNAUTHORIZED
        );
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse(
            new ApiResponse(
                false,
                $authException ? $authException->getMessage() : 'Authentication Required'
            ),
            Response::HTTP_UNAUTHORIZED
        );
    }
}
