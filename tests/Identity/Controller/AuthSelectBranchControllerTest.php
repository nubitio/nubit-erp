<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Auth\SelectedBranchContext;
use App\Identity\Controller\AuthSelectBranchController;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Entity\Company;
use App\Organization\Repository\BranchRepository;
use App\Organization\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Auth\JWTAuthenticator;
use Nubit\AdminBundle\Auth\JWTManager;
use Nubit\AdminBundle\Auth\ResponseModeResolver;
use Nubit\AdminBundle\Auth\TokenGenerator;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Nubit\Platform\Tenant\Context\TenantContext;

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

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testReturnsForbiddenWhenRegularUserSelectsAnotherBranch(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $user->method('getId')->willReturn(7);

        $company = new Company();
        $branch = (new Branch())->setName('Otra sucursal')->setCompany($company);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);
        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('findOneBy')->willReturn($company);

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

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testReturnsForbiddenWhenBranchBelongsToAnotherCompany(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $user->method('getId')->willReturn(8);

        $currentCompany = new Company();
        $branch = (new Branch())->setName('Empresa ajena')->setCompany(new Company());
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

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAllowsPrivilegedUserForBranchInCurrentTenantAndCompany(): void
    {
        $user = $this->createStub(User::class);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $user->method('getDeletedAt')->willReturn(null);
        $company = new Company();
        $branch = (new Branch())->setName('Sucursal autorizada')->setCompany($company);
        $companyRepository = $this->createStub(CompanyRepository::class);
        $companyRepository->method('findOneBy')->willReturn($company);

        $controller = new AuthSelectBranchController(
            $this->createStub(Security::class),
            $this->createStub(BranchRepository::class),
            $companyRepository,
            $this->tenantContext(),
            new \ReflectionClass(TokenGenerator::class)->newInstanceWithoutConstructor(),
            new SelectedBranchContext(),
            new ResponseModeResolver(),
            new \ReflectionClass(JWTAuthenticator::class)->newInstanceWithoutConstructor(),
            new NullLogger(),
        );

        $reflection = new \ReflectionMethod($controller, 'canSelectBranch');
        self::assertTrue($reflection->invoke($controller, $user, $branch));
    }

    private function tenantContext(): TenantContext
    {
        $context = $this->createStub(TenantContext::class);
        $context->method('getTenantId')->willReturn('tenant-1');

        return $context;
    }
}
