<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Operator\Api;

use App\ControlPlane\Operator\Api\ImpersonationApiController;
use App\ControlPlane\Tenant\Application\TenantService;
use App\Efact\Audit\AuditLogger;
use App\Identity\Auth\UserProvider;
use App\Identity\Entity\User;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\AdminBundle\Auth\RefreshTokenStoreInterface;
use Nubit\AdminBundle\Auth\TokenClaimsProviderInterface;
use Nubit\AdminBundle\Auth\TokenGenerator;
use Nubit\Platform\Tenant\Context\TenantContext;
use Nubit\Platform\Tenant\Contract\TenantConnectionSwitcherInterface;
use Nubit\Platform\Tenant\Runtime\TenantRuntime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class ImpersonationApiControllerTest extends OperatorApiTestCase
{
    public function testInvokeReturnsAccessTokenAndAuditsWithoutLeakingRefreshToken(): void
    {
        $tenant = ['id' => 5, 'name' => 'acme', 'primary_domain' => 'acme.example.com'];

        $tenantService = $this->createStub(TenantService::class);
        $tenantService->method('getTenant')->willReturn($tenant);

        $targetUser = $this->createStub(User::class);
        $targetUser->method('getUserIdentifier')->willReturn('jdoe');
        $targetUser->method('getRoles')->willReturn(['ROLE_USER']);
        $targetUser->method('getId')->willReturn(42);

        $userProvider = $this->createStub(UserProvider::class);
        $userProvider->method('loadUserByIdentifier')->willReturn($targetUser);

        $connectionSwitcher = $this->createStub(TenantConnectionSwitcherInterface::class);
        $tenantRuntime = new TenantRuntime($connectionSwitcher, new TenantContext());

        $claimsProvider = $this->createStub(TokenClaimsProviderInterface::class);
        $claimsProvider->method('claims')->willReturn(['impersonatedBy' => 'root']);
        $claimsProvider->method('userData')->willReturn(['username' => 'jdoe']);

        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $jwtManager->method('encode')->willReturn('signed.jwt.token');

        $refreshTokenStore = $this->createStub(RefreshTokenStoreInterface::class);

        $tokenGenerator = new TokenGenerator($jwtManager, $claimsProvider, $refreshTokenStore, 900, 604800);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with('user.impersonate', $this->arrayHasKey('target_username'), 5, 'root');

        $controller = new ImpersonationApiController(
            $tenantService,
            $tenantRuntime,
            $userProvider,
            $tokenGenerator,
            $auditLogger,
        );
        $this->setControllerContainer($controller);
        $this->authenticateAs('root');

        $request = Request::create(
            '/api/control-plane/tenants/5/impersonate',
            'POST',
            content: json_encode(['username' => 'jdoe'], JSON_THROW_ON_ERROR),
        );

        $response = $controller->__invoke(5, $request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        $this->assertSame('signed.jwt.token', $payload['token']);
        $this->assertArrayNotHasKey('refreshToken', $payload);
        $this->assertSame(['username' => 'jdoe'], $payload['user']);
    }

    public function testInvokeReturnsNotFoundWhenTargetUserMissing(): void
    {
        $tenant = ['id' => 5, 'name' => 'acme'];

        $tenantService = $this->createStub(TenantService::class);
        $tenantService->method('getTenant')->willReturn($tenant);

        $userProvider = $this->createStub(UserProvider::class);
        $userProvider->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());

        $connectionSwitcher = $this->createStub(TenantConnectionSwitcherInterface::class);
        $tenantRuntime = new TenantRuntime($connectionSwitcher, new TenantContext());

        $claimsProvider = $this->createStub(TokenClaimsProviderInterface::class);
        $jwtManager = $this->createStub(JWTManagerInterface::class);
        $refreshTokenStore = $this->createStub(RefreshTokenStoreInterface::class);
        $tokenGenerator = new TokenGenerator($jwtManager, $claimsProvider, $refreshTokenStore, 900, 604800);

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger->expects($this->never())->method('log');

        $controller = new ImpersonationApiController(
            $tenantService,
            $tenantRuntime,
            $userProvider,
            $tokenGenerator,
            $auditLogger,
        );
        $this->setControllerContainer($controller);
        $this->authenticateAs('root');

        $request = Request::create(
            '/api/control-plane/tenants/5/impersonate',
            'POST',
            content: json_encode(['username' => 'ghost'], JSON_THROW_ON_ERROR),
        );

        $response = $controller->__invoke(5, $request);

        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
