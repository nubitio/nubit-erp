<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Override;
use Throwable;
use DateTimeImmutable;
use App\Entity\Branch;
use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\ExpiredException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class JWTAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string AUTH_HEADER = 'Authorization';

    public const string AUTH_COOKIE = 'AUTH_TOKEN';

    public const string REFRESH_COOKIE = 'REFRESH_TOKEN';

    public const string LOGIN_ROUTE = 'auth_login';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserProvider $userProvider,
        private readonly JWTManager $jwtManager,
        private readonly LoggerInterface $logger,
        private readonly int $accessTokenTtl,
        private readonly int $refreshTokenTtl,
    ) {
    }

    #[Override]
    public function supports(Request $request): ?bool
    {
        $isAuthHeaderPresent = $request->headers->has(self::AUTH_HEADER);
        $isAuthCookiePresent = $request->cookies->has(self::AUTH_COOKIE);
        $isLoginRoute = self::LOGIN_ROUTE === $request->attributes->get('_route');
        $isPostMethod = $request->isMethod('POST');

        return (($isAuthHeaderPresent || $isAuthCookiePresent) && !$isLoginRoute) || ($isPostMethod && $isLoginRoute);
    }

    public function getCredentials(Request $request): array
    {
        $username = '';
        $password = '';
        $branchId = null;

        if (self::LOGIN_ROUTE === $request->attributes->get('_route')) {
            $data = json_decode($request->getContent(), true);

            if (is_array($data)) {
                $username = $data['username'] ?? '';
                $password = $data['password'] ?? '';
                $branchId = $data['branchId'] ?? null;
            }
        }

        $jwtToken = $request->headers->get(self::AUTH_HEADER);
        if (!$jwtToken) {
            $jwtToken = $request->cookies->get(self::AUTH_COOKIE);
        }

        return [
            'username' => $username,
            'password' => $password,
            'branchId' => $branchId,
            'token' => $jwtToken,
        ];
    }

    #[Override]
    public function authenticate(Request $request): Passport
    {
        $credentials = $this->getCredentials($request);
        $jwtToken = $credentials['token'];

        if ($jwtToken) {
            try {
                $jwtToken = str_replace('Bearer ', '', $jwtToken);
                $tokenData = $this->jwtManager->decode($jwtToken);
            } catch (ExpiredException) {
                throw new AuthenticationException('Su sesión ha expirado', Response::HTTP_UNAUTHORIZED);
            } catch (Throwable $e) {
                $this->logger->error('Error decoding JWT token', ['exception' => $e]);
                throw new AuthenticationException('Token inválido', Response::HTTP_UNAUTHORIZED);
            }

            $username = $tokenData['username'];
            $branchId = $tokenData['branchId'] ?? $credentials['branchId'];
            $branch = null;
            if ($branchId) {
                $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
            }

            $passport = new SelfValidatingPassport(
                new UserBadge($username, $this->userProvider->loadUserByIdentifier(...))
            );
            $passport->setAttribute('branchId', $branchId);
            $passport->setAttribute('branchName', $branch?->getName());

            return $passport;
        }

        return $this->getPassport($credentials);
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();
        $branchId = $passport->getAttribute('branchId') ?? null;
        $now = new DateTimeImmutable();
        $nowTimestamp = $now->getTimestamp();
        $accessTokenExpiresAt = $nowTimestamp + $this->accessTokenTtl;
        $refreshTokenExpiresAt = $nowTimestamp + $this->refreshTokenTtl;

        $branch = null;
        if ($branchId) {
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        }

        if (!$user instanceof User) {
            throw new AuthenticationException('Invalid user');
        }

        $payload = [
            'iat' => $nowTimestamp,
            'type' => 'access',
        ];

        $payload['username'] = $user->getUsername();
        $payload['userId'] = $user->getId();
        $payload['role'] = $user->getRole()?->getId();
        $payload['branchId'] = $branch?->getId();
        $payload['branchName'] = $branch?->getName();
        $payload['roles'] = $user->getRoles();

        $accessToken = $this->jwtManager->encode([
            ...$payload,
            'exp' => $accessTokenExpiresAt,
        ]);

        $refreshTokenJti = bin2hex(random_bytes(16));
        $refreshToken = $this->jwtManager->encode([
            ...$payload,
            'exp' => $refreshTokenExpiresAt,
            'type' => 'refresh',
            'jti' => $refreshTokenJti,
        ]);

        $this->storeRefreshToken($refreshTokenJti, $refreshToken, $user, $refreshTokenExpiresAt);

        $jwtToken = new JWTAuthenticationToken(
            $passport->getUser(),
            $firewallName,
            $passport->getUser()->getRoles(),
            $passport->getAttribute('token') ?? $accessToken
        );

        if ($branchId) {
            $jwtToken->setAttribute('branchId', $branchId);
            $jwtToken->setAttribute('branchName', $branch?->getName());
        }

        $jwtToken->setAttribute('refreshToken', $refreshToken);
        $jwtToken->setAttribute('accessTokenExpiresAt', $accessTokenExpiresAt);
        $jwtToken->setAttribute('refreshTokenExpiresAt', $refreshTokenExpiresAt);

        return $jwtToken;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $isLoginRoute = self::LOGIN_ROUTE === $request->attributes->get('_route');
        if (!$isLoginRoute) {
            return null;
        }

        if (!$token instanceof JWTAuthenticationToken) {
            return null;
        }

        $user = $token->getUser();

        if ($user instanceof User) {
            $branchId = null;
            $branchName = null;
            try {
                $branchId = $token->getAttribute('branchId');
                $branchName = $token->getAttribute('branchName');
            } catch (Throwable) {
                $this->logger->error('Token does not have branchId or branchName attribute');
            }

            $accessTokenExpiresAt = time() + $this->accessTokenTtl;
            $refreshTokenExpiresAt = time() + $this->refreshTokenTtl;

            try {
                $accessTokenExpiresAt = $token->getAttribute('accessTokenExpiresAt');
            } catch (Throwable) {
                $this->logger->error('Token does not have accessTokenExpiresAt attribute');
            }

            try {
                $refreshTokenExpiresAt = $token->getAttribute('refreshTokenExpiresAt');
            } catch (Throwable) {
                $this->logger->error('Token does not have refreshTokenExpiresAt attribute');
            }

            $response = new JsonResponse([
                'user' => [
                    'username' => $user->getUsername(),
                    'userId' => $user->getId(),
                    'role' => $user->getRole()?->getId(),
                    'roles' => $user->getRoles(),
                    'branchId' => $branchId,
                    'branchName' => $branchName,
                ],
            ]);

            $accessCookie = Cookie::create(
                self::AUTH_COOKIE,
                $token->getCredentials(),
                $accessTokenExpiresAt,
                '/',
                null,
                true
            );

            $refreshCookie = Cookie::create(
                self::REFRESH_COOKIE,
                $token->getAttribute('refreshToken'),
                $refreshTokenExpiresAt,
                '/',
                null,
                true
            );

            $response->headers->setCookie($accessCookie);
            $response->headers->setCookie($refreshCookie);

            return $response;
        }

        return null;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        throw $exception;
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'message' => $authException instanceof AuthenticationException ? $authException->getMessage(
            ) : 'Authentication Required',
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function getPassport(array $credentials): Passport
    {
        $username = $credentials['username'];
        $password = $credentials['password'];
        $branchId = $credentials['branchId'];

        $userBadge = new UserBadge($username, $this->userProvider->loadUserByIdentifier(...));
        $passwordUpgradeBadge = new PasswordUpgradeBadge($password, $this->userProvider);
        $passport = new Passport($userBadge, new PasswordCredentials($password), [new RememberMeBadge()]);
        $passport->addBadge($passwordUpgradeBadge);

        $branch = null;
        if ($branchId) {
            $branch = $this->entityManager->getRepository(Branch::class)->find($branchId);
        }

        $passport->setAttribute('branchId', $branchId);
        $passport->setAttribute('branchName', $branch?->getName());

        return $passport;
    }

    private function storeRefreshToken(string $jti, string $refreshToken, User $user, int $refreshTokenExpiresAt): void
    {
        $refreshTokenEntity = new RefreshToken()
            ->setJti($jti)
            ->setTokenHash(hash('sha256', $refreshToken))
            ->setUser($user)
            ->setExpiresAt(new DateTimeImmutable()->setTimestamp($refreshTokenExpiresAt));

        $this->entityManager->persist($refreshTokenEntity);
        $this->entityManager->flush();
    }
}
