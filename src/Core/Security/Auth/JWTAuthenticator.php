<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use App\Entity\User;
use Firebase\JWT\ExpiredException;
use Override;
use Psr\Log\LoggerInterface;
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
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class JWTAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public const string AUTH_HEADER = 'Authorization';

    public const string AUTH_COOKIE = 'AUTH_TOKEN';

    public const string REFRESH_COOKIE = 'REFRESH_TOKEN';

    public const string REFRESH_HEADER = 'X-Refresh-Token';

    public const string LOGIN_ROUTE = 'auth_login';

    public function __construct(
        private readonly UserProvider $userProvider,
        private readonly JWTManager $jwtManager,
        private readonly LoggerInterface $logger,
        private readonly TokenGeneratorService $tokenGenerator,
        private readonly ClientDetectorService $clientDetector,
        private readonly CookieFactoryService $cookieFactory,
        private readonly TranslatorInterface $translator,
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

        if (self::LOGIN_ROUTE === $request->attributes->get('_route')) {
            $data = json_decode($request->getContent(), true);

            if (is_array($data)) {
                $username = $data['username'] ?? '';
                $password = $data['password'] ?? '';
            }
        }

        $jwtToken = $request->headers->get(self::AUTH_HEADER);
        if (!$jwtToken) {
            $jwtToken = $request->cookies->get(self::AUTH_COOKIE);
        }

        return [
            'username' => $username,
            'password' => $password,
            'token' => $jwtToken,
        ];
    }

    #[Override]
    public function authenticate(Request $request): Passport
    {
        $credentials = $this->getCredentials($request);
        $jwtToken = $credentials['token'];

        if ($jwtToken) {
            return $this->authenticateWithJWT($jwtToken, $credentials);
        }

        return $this->authenticateWithCredentials($credentials);
    }

    private function authenticateWithJWT(string $jwtToken, array $credentials): SelfValidatingPassport
    {
        try {
            $jwtToken = str_replace('Bearer ', '', $jwtToken);
            $tokenData = $this->jwtManager->decode($jwtToken);
        } catch (ExpiredException $e) {
            $this->logger->warning('JWT token expired', [
                'exception' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            throw new AuthenticationException('Su sesión ha expirado', Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            $this->logger->error('Error decoding JWT token', [
                'exception' => $e->getMessage(),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'trace' => $e->getTraceAsString(),
            ]);
            throw new AuthenticationException('Token inválido', Response::HTTP_UNAUTHORIZED);
        }

        $username = $tokenData['username'] ?? null;
        if (!$username) {
            $this->logger->error('JWT token missing username claim');
            throw new AuthenticationException('Token inválido', Response::HTTP_UNAUTHORIZED);
        }

        $passport = new SelfValidatingPassport(
            new UserBadge($username, $this->userProvider->loadUserByIdentifier(...))
        );
        $passport->setAttribute('is_login', false);
        $passport->setAttribute('token', $jwtToken);

        $this->logger->info('User authenticated with JWT', [
            'username' => $username,
            'user_id' => $tokenData['userId'] ?? null,
        ]);

        return $passport;
    }

    private function authenticateWithCredentials(array $credentials): Passport
    {
        $username = $credentials['username'];
        $password = $credentials['password'];

        if (empty($username) || empty($password)) {
            $this->logger->warning('Login attempt with empty credentials');
            throw new AuthenticationException('Credenciales inválidas', Response::HTTP_UNAUTHORIZED);
        }

        $userBadge = new UserBadge($username, $this->userProvider->loadUserByIdentifier(...));
        $passwordUpgradeBadge = new PasswordUpgradeBadge($password, $this->userProvider);
        $passport = new Passport($userBadge, new PasswordCredentials($password), [new RememberMeBadge()]);
        $passport->addBadge($passwordUpgradeBadge);

        $passport->setAttribute('is_login', true);

        return $passport;
    }

    #[Override]
    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $user = $passport->getUser();
        $isLogin = $passport->getAttribute('is_login') ?? true;

        if (!$user instanceof User) {
            $this->logger->error('Invalid user type in passport', [
                'user_class' => get_class($user),
            ]);
            throw new AuthenticationException('Invalid user');
        }

        if (!$isLogin) {
            return $this->createTokenFromExisting($user, $passport, $firewallName);
        }

        return $this->createTokenFromLogin($user, $firewallName);
    }

    private function createTokenFromExisting(
        User $user,
        Passport $passport,
        string $firewallName,
    ): JWTAuthenticationToken {
        $existingToken = $passport->getAttribute('token');

        return new JWTAuthenticationToken(
            $user,
            $firewallName,
            $user->getRoles(),
            $existingToken
        );
    }

    private function createTokenFromLogin(
        User $user,
        string $firewallName
    ): JWTAuthenticationToken {
        try {
            $tokenPair = $this->tokenGenerator->generateTokenPair($user);
        } catch (Throwable $e) {
            $this->logger->error('Failed to generate token pair', [
                'user_id' => $user->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new AuthenticationException('Error al generar tokens');
        }

        $jwtToken = new JWTAuthenticationToken(
            $user,
            $firewallName,
            $user->getRoles(),
            $tokenPair->accessToken
        );

        $jwtToken->setAttribute('refreshToken', $tokenPair->refreshToken);
        $jwtToken->setAttribute('accessTokenExpiresAt', $tokenPair->accessTokenExpiresAt);
        $jwtToken->setAttribute('refreshTokenExpiresAt', $tokenPair->refreshTokenExpiresAt);

        $this->logger->info('Token pair generated for user', [
            'user_id' => $user->getId(),
            'username' => $user->getUsername(),
            'expires_at' => date('Y-m-d H:i:s', $tokenPair->accessTokenExpiresAt),
        ]);

        return $jwtToken;
    }

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
        if (!$user instanceof User) {
            return null;
        }

        $isMobileClient = $this->clientDetector->isMobileClient($request);
        $responseData = $this->buildResponseData($user);

        if ($isMobileClient) {
            return $this->createMobileResponse($token, $responseData);
        }

        return $this->createWebResponse($token, $responseData);
    }

    private function buildResponseData(User $user): array
    {
        return [
            'user' => [
                'username' => $user->getUsername(),
                'userId' => $user->getId(),
                'role' => $user->getRole()?->getId(),
                'roles' => $user->getRoles(),
            ],
        ];
    }

    private function createMobileResponse(JWTAuthenticationToken $token, array $responseData): JsonResponse
    {
        $refreshToken = $this->getTokenAttributeSafely($token, 'refreshToken');
        $accessTokenExpiresAt = $this->getTokenAttributeSafely($token, 'accessTokenExpiresAt');

        if ($refreshToken && $accessTokenExpiresAt) {
            $responseData['token'] = $token->getCredentials();
            $responseData['refreshToken'] = $refreshToken;
            $responseData['expiresAt'] = $accessTokenExpiresAt;
        }

        return new JsonResponse($responseData);
    }

    private function createWebResponse(JWTAuthenticationToken $token, array $responseData): JsonResponse
    {
        $response = new JsonResponse($responseData);

        $refreshToken = $this->getTokenAttributeSafely($token, 'refreshToken');
        $accessTokenExpiresAt = $this->getTokenAttributeSafely($token, 'accessTokenExpiresAt');
        $refreshTokenExpiresAt = $this->getTokenAttributeSafely($token, 'refreshTokenExpiresAt');

        if ($refreshToken && $accessTokenExpiresAt && $refreshTokenExpiresAt) {
            $accessCookie = $this->cookieFactory->createSecureCookie(
                self::AUTH_COOKIE,
                $token->getCredentials(),
                $accessTokenExpiresAt
            );

            $refreshCookie = $this->cookieFactory->createSecureCookie(
                self::REFRESH_COOKIE,
                $refreshToken,
                $refreshTokenExpiresAt
            );

            $response->headers->setCookie($accessCookie);
            $response->headers->setCookie($refreshCookie);
        }

        return $response;
    }

    private function getTokenAttributeSafely(JWTAuthenticationToken $token, string $attribute): mixed
    {
        try {
            return $token->getAttribute($attribute);
        } catch (Throwable $e) {
            $this->logger->warning('Token attribute not found', [
                'attribute' => $attribute,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $this->logger->warning('Authentication failure', [
            'exception' => $exception->getMessage(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'route' => $request->attributes->get('_route'),
        ]);

        $translatedMessage = $this->translator->trans(
            $exception->getMessageKey(),
            $exception->getMessageData(),
            'security'
        );

        return new JsonResponse([
            'message' => $translatedMessage,
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        $message = $authException?->getMessage() ?? 'Authentication Required';

        $this->logger->info('Authentication required', [
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'route' => $request->attributes->get('_route'),
        ]);

        return new JsonResponse([
            'message' => $message,
        ], Response::HTTP_UNAUTHORIZED);
    }
}
