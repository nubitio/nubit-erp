<?php

namespace App\Auth\Domain;

use App\Core\Presentation\Security\JWTAuthenticationToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JWTAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string AUTH_HEADER = 'Authorization';
    public const string LOGIN_ROUTE = '_api_/auth_post';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::AUTH_HEADER)
            && self::LOGIN_ROUTE !== $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        $apiToken = $request->headers->get(self::AUTH_HEADER);

        if (null === $apiToken) {
            throw new AuthenticationException('No API token provided', Response::HTTP_UNAUTHORIZED);
        }

        $apiToken = str_replace('Bearer ', '', $apiToken);

        try {
            $tokenData = JWT::decode(
                $apiToken,
                new Key($_ENV['APP_SECRET'], 'HS256')
            );
        } catch (ExpiredException $e) {
            throw new AuthenticationException('Expired token', Response::HTTP_UNAUTHORIZED);
        } catch (\Throwable $e) {
            throw new AuthenticationException('Invalid token', Response::HTTP_UNAUTHORIZED);
        }

        $userId = $tokenData->sub;

        $passport = new SelfValidatingPassport(
            new UserBadge(strval($userId), function ($userId) {
                return $this->loadUser($userId);
            }),
        );

        $passport->setAttribute('payload', $tokenData);
        $passport->setAttribute('token', $apiToken);

        return $passport;
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return null;
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception,
    ): ?Response {
        return new JsonResponse([
            'message' => strtr(
                $exception->getMessageKey(),
                $exception->getMessageData()
            ),
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(
        Request $request,
        ?AuthenticationException $authException = null,
    ): Response {
        return new JsonResponse([
            'message' => $authException ? $authException->getMessage()
                : 'Authentication Required',
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function createToken(
        Passport $passport,
        string $firewallName,
    ): TokenInterface {
        return new JWTAuthenticationToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles(),
            $passport->getAttribute('token')
        );
    }

    private function loadUser(int $userId): UserInterface
    {
        $user = $this->entityManager->find(User::class, $userId);

        if (null === $user) {
            throw new AuthenticationException('User not found', Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }
}
