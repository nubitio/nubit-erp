<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Auth\UserProvider;
use App\Identity\Controller\AuthImpersonationConsumeController;
use App\Identity\Entity\User;
use Firebase\JWT\ExpiredException;
use Nubit\AdminBundle\Auth\CookieFactory;
use Nubit\AdminBundle\Auth\CsrfProtectionListener;
use Nubit\AdminBundle\Auth\CsrfTokenPolicy;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\AdminBundle\Auth\TokenClaimsProviderInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class AuthImpersonationConsumeControllerTest extends TestCase
{
    private function withContainer(AuthImpersonationConsumeController $controller): AuthImpersonationConsumeController
    {
        $controller->setContainer(new Container());

        return $controller;
    }

    public function testReturnsBadRequestWhenTokenMissing(): void
    {
        $controller = $this->withContainer(
            new AuthImpersonationConsumeController(
                $this->createStub(JWTManagerInterface::class),
                $this->createStub(UserProvider::class),
                new CookieFactory(),
                $this->createStub(TokenClaimsProviderInterface::class),
            ),
        );

        $request = Request::create(
            '/api/auth/impersonate/consume',
            'POST',
            content: json_encode([], JSON_THROW_ON_ERROR),
        );
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testReturnsUnauthorizedWhenTokenExpired(): void
    {
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager->method('decode')->willThrowException(new ExpiredException());

        $controller = $this->withContainer(
            new AuthImpersonationConsumeController(
                $jwtManager,
                $this->createStub(UserProvider::class),
                new CookieFactory(),
                $this->createStub(TokenClaimsProviderInterface::class),
            ),
        );

        $request = Request::create('/api/auth/impersonate/consume', 'POST', content: json_encode([
            'token' => 'expired.jwt',
        ], JSON_THROW_ON_ERROR));
        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsUnauthorizedWhenTokenIsNotAnImpersonationToken(): void
    {
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager->method('decode')->willReturn(['username' => 'jdoe']);

        $controller = $this->withContainer(
            new AuthImpersonationConsumeController(
                $jwtManager,
                $this->createStub(UserProvider::class),
                new CookieFactory(),
                $this->createStub(TokenClaimsProviderInterface::class),
            ),
        );

        $request = Request::create('/api/auth/impersonate/consume', 'POST', content: json_encode([
            'token' => 'regular.jwt',
        ], JSON_THROW_ON_ERROR));
        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsUnauthorizedOnTenantMismatch(): void
    {
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager
            ->method('decode')
            ->willReturn([
                'username' => 'jdoe',
                'impersonatedBy' => 'root',
                'tenantName' => 'acme',
            ]);

        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'other-tenant', 'other-tenant.example.com', null);

        $controller = $this->withContainer(
            new AuthImpersonationConsumeController(
                $jwtManager,
                $this->createStub(UserProvider::class),
                new CookieFactory(),
                $this->createStub(TokenClaimsProviderInterface::class),
                $tenantContext,
            ),
        );

        $request = Request::create('/api/auth/impersonate/consume', 'POST', content: json_encode([
            'token' => 'cross.tenant.jwt',
        ], JSON_THROW_ON_ERROR));
        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsNotFoundWhenUserMissing(): void
    {
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager
            ->method('decode')
            ->willReturn([
                'username' => 'ghost',
                'impersonatedBy' => 'root',
            ]);

        $userProvider = $this->createStub(UserProvider::class);
        $userProvider->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());

        $controller = $this->withContainer(
            new AuthImpersonationConsumeController(
                $jwtManager,
                $userProvider,
                new CookieFactory(),
                $this->createStub(TokenClaimsProviderInterface::class),
            ),
        );

        $request = Request::create('/api/auth/impersonate/consume', 'POST', content: json_encode([
            'token' => 'valid.jwt',
        ], JSON_THROW_ON_ERROR));
        $response = $controller($request);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testSetsAuthCookieAndReturnsUserOnSuccess(): void
    {
        $expiresAt = time() + 900;
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager
            ->method('decode')
            ->willReturn([
                'username' => 'jdoe',
                'impersonatedBy' => 'root',
                'exp' => $expiresAt,
            ]);

        $targetUser = $this->createStub(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('jdoe');

        $userProvider = $this->createStub(UserProvider::class);
        $userProvider->method('loadUserByIdentifier')->willReturn($targetUser);

        $claimsProvider = $this->createStub(TokenClaimsProviderInterface::class);
        $claimsProvider->method('userData')->willReturn(['username' => 'jdoe', 'roles' => ['ROLE_USER']]);

        $controller = $this->withContainer(
            new AuthImpersonationConsumeController($jwtManager, $userProvider, new CookieFactory(), $claimsProvider),
        );

        $request = Request::create('/api/auth/impersonate/consume', 'POST', content: json_encode([
            'token' => 'valid.jwt',
        ], JSON_THROW_ON_ERROR));
        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true);
        self::assertIsArray($payload);
        self::assertTrue($payload['ok']);
        self::assertSame(['username' => 'jdoe', 'roles' => ['ROLE_USER']], $payload['user']);

        $cookie = $response->headers->getCookies()[0] ?? null;
        self::assertNotNull($cookie);
        self::assertSame(JWTAuthenticator::AUTH_COOKIE, $cookie->getName());
        self::assertSame('valid.jwt', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());

        $cookies = [];
        foreach ($response->headers->getCookies() as $responseCookie) {
            $cookies[$responseCookie->getName()] = $responseCookie;
        }
        static::assertArrayHasKey(CsrfTokenPolicy::COOKIE_NAME, $cookies);
        $csrfCookie = $cookies[CsrfTokenPolicy::COOKIE_NAME];
        $csrf = $csrfCookie->getValue();
        static::assertIsString($csrf);
        static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $csrf);
        static::assertFalse($csrfCookie->isHttpOnly());
        static::assertSame($cookie->getExpiresTime(), $csrfCookie->getExpiresTime());
        static::assertSame($cookie->getDomain(), $csrfCookie->getDomain());
        static::assertSame($cookie->isSecure(), $csrfCookie->isSecure());

        // The next step of the bridge is a cookie-authenticated mutation.
        $selectBranch = Request::create('https://tenant.example.com/api/auth/select-branch', 'POST', cookies: [
            JWTAuthenticator::AUTH_COOKIE => $cookie->getValue(),
            CsrfTokenPolicy::COOKIE_NAME => $csrf,
        ]);
        $selectBranch->headers->set(CsrfTokenPolicy::HEADER_NAME, $csrf);
        $selectBranch->headers->set('Origin', 'https://tenant.example.com');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $selectBranch,
            HttpKernelInterface::MAIN_REQUEST,
        );
        $protection = new CsrfProtectionListener(new NullLogger());
        $protection($event);
        static::assertFalse($event->hasResponse());

        $selectBranch->headers->remove(CsrfTokenPolicy::HEADER_NAME);
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $selectBranch,
            HttpKernelInterface::MAIN_REQUEST,
        );
        $protection($event);
        static::assertSame(Response::HTTP_FORBIDDEN, $event->getResponse()?->getStatusCode());
    }
}
