<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Auth\SelectedBranchContext;
use App\Identity\Controller\AuthSelectBranchController;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use Nubit\AdminBundle\Auth\RefreshTokenStoreInterface;
use Nubit\AdminBundle\Auth\ResponseModeResolver;
use Nubit\AdminBundle\Auth\TokenClaimsProviderInterface;
use Nubit\AdminBundle\Auth\TokenGenerator;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class AuthSelectBranchControllerTest extends TestCase
{
    public function testReturnsUnauthorizedWhenUserIsNotAuthenticated(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);
        $tokenGenerator = new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor();
        $clientDetector = new SelectedBranchContext();
        $cookieFactory = new ResponseModeResolver();

        $controller = new AuthSelectBranchController(
            $security,
            $this->createMock(BranchRepository::class),
            $this->createMock(CompanyRepository::class),
            $this->tenantContext(),
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create('/api/auth/select-branch', 'POST', [], [], [], [], json_encode(['branchId' => '1']));
        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsBadRequestWhenBranchIdIsMissing(): void
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('getUser')
            ->willReturn(
                new User()
                    ->setUsername('tester')
                    ->setPassword('hash'),
            );
        $tokenGenerator = new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor();
        $clientDetector = new SelectedBranchContext();
        $cookieFactory = new ResponseModeResolver();

        $controller = new AuthSelectBranchController(
            $security,
            $this->createMock(BranchRepository::class),
            $this->createMock(CompanyRepository::class),
            $this->tenantContext(),
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create('/api/auth/select-branch', 'POST', [], [], [], [], json_encode([]));
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testReturnsForbiddenWhenBranchIsDisabled(): void
    {
        $security = $this->createStub(Security::class);
        $security
            ->method('getUser')
            ->willReturn(
                new User()
                    ->setUsername('tester')
                    ->setPassword('hash'),
            );

        $branch = new Branch()
            ->setName('Sucursal cerrada')
            ->setIsActive(false);

        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);

        $tokenGenerator = new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor();
        $clientDetector = new SelectedBranchContext();
        $cookieFactory = new ResponseModeResolver();

        $controller = new AuthSelectBranchController(
            $security,
            $branchRepository,
            $this->createStub(CompanyRepository::class),
            $this->tenantContext(),
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create(
            '/api/auth/select-branch',
            'POST',
            [],
            [],
            [],
            [],
            (string) json_encode(['branchId' => 'b-1']),
        );
        $response = $controller($request);

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertArrayNotHasKey('token', json_decode(
            $response->getContent(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function testReturnsForbiddenWhenUserSelectsBranchFromAnotherCompany(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $user->method('getId')->willReturn(7);

        $branch = new Branch()
            ->setName('Otra sucursal')
            ->setCompany(new Company());

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('findOneBy')->willReturn(new Company());

        $controller = new AuthSelectBranchController(
            $security,
            $branchRepository,
            $companyRepository,
            $this->tenantContext(),
            new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor(),
            new SelectedBranchContext(),
            new ResponseModeResolver(),
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            new NullLogger(),
        );

        $response = $controller(Request::create(
            '/api/auth/select-branch',
            'POST',
            [],
            [],
            [],
            [],
            (string) json_encode(['branchId' => 'b-2']),
        ));

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertArrayNotHasKey('token', json_decode(
            $response->getContent(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function testReturnsForbiddenWhenBranchBelongsToAnotherCompany(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $user->method('getId')->willReturn(8);

        $currentCompany = new Company();
        $branch = new Branch()
            ->setName('Empresa ajena')
            ->setCompany(new Company());
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('findOneBy')->willReturn($currentCompany);

        $controller = new AuthSelectBranchController(
            $security,
            $branchRepository,
            $companyRepository,
            $this->tenantContext(),
            new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor(),
            new SelectedBranchContext(),
            new ResponseModeResolver(),
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            new NullLogger(),
        );

        $response = $controller(Request::create(
            '/api/auth/select-branch',
            'POST',
            [],
            [],
            [],
            [],
            (string) json_encode(['branchId' => 'b-cross-company']),
        ));

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        static::assertArrayNotHasKey('token', json_decode(
            $response->getContent(),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        ));
    }

    public function testIssuesTokensForCashierInCurrentTenantCompany(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_CASHIER', 'ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $user->method('getUserIdentifier')->willReturn('cashier');
        $company = new Company();
        $branch = new Branch()
            ->setName('Sucursal autorizada')
            ->setCompany($company);
        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('findOneBy')->willReturn($company);

        $jwtManager = $this->createMock(JWTManagerInterface::class);
        $jwtManager->method('encode')->willReturnOnConsecutiveCalls('access-token', 'refresh-token');
        $claimsProvider = $this->createStub(TokenClaimsProviderInterface::class);
        $claimsProvider->method('claims')->willReturn(['userId' => 7, 'branchId' => 'branch-1']);
        $claimsProvider->method('userData')->willReturn(['id' => 7]);
        $refreshTokenStore = $this->createMock(RefreshTokenStoreInterface::class);
        $refreshTokenStore->expects($this->once())->method('save');

        $controller = new AuthSelectBranchController(
            $this->securityFor($user),
            $branchRepository,
            $companyRepository,
            $this->tenantContext(),
            new TokenGenerator($jwtManager, $claimsProvider, $refreshTokenStore, 900, 86_400),
            new SelectedBranchContext(),
            new ResponseModeResolver(),
            $this->createMock(JWTAuthenticator::class),
            new NullLogger(),
        );

        $response = $controller(Request::create(
            '/api/auth/select-branch',
            'POST',
            [],
            [],
            [],
            [],
            (string) json_encode(['branchId' => 'branch-1', 'response_mode' => 'json']),
        ));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            'access-token',
            json_decode($response->getContent(), associative: true, flags: JSON_THROW_ON_ERROR)['token'],
        );
    }

    private function securityFor(User $user): Security
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return $security;
    }

    private function tenantContext(): TenantContext&Stub
    {
        $context = $this->createStub(TenantContext::class);
        $context->method('getTenantId')->willReturn(1);

        return $context;
    }
}
