<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthSelectBranchController;
use App\Core\Context\TenantContext;
use App\Core\Security\Auth\ClientDetectorService;
use App\Core\Security\Auth\CookieFactoryService;
use App\Core\Security\Auth\MercureSubscriberTokenService;
use App\Core\Security\Auth\TokenGeneratorService;
use App\Entity\User;
use App\Core\Security\Auth\JWTAuthenticator;
use App\Core\Security\Auth\JWTManager;
use App\Entity\Branch;
use App\Entity\Role;
use App\Repository\BranchRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthSelectBranchControllerTest extends TestCase
{
    public function testReturnsUnauthorizedWhenUserIsNotAuthenticated(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);
        $tokenGenerator = (new \ReflectionClass(TokenGeneratorService::class))->newInstanceWithoutConstructor();
        $clientDetector = (new \ReflectionClass(ClientDetectorService::class))->newInstanceWithoutConstructor();
        $cookieFactory = (new \ReflectionClass(CookieFactoryService::class))->newInstanceWithoutConstructor();

        $controller = new AuthSelectBranchController(
            $security,
            $this->createMock(BranchRepository::class),
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            (new \ReflectionClass(MercureSubscriberTokenService::class))->newInstanceWithoutConstructor(),
            new TenantContext(),
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create('/api/auth/select-branch', 'POST', [], [], [], [], json_encode(['branchId' => '1']));
        $response = $controller($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturnsBadRequestWhenBranchIdIsMissing(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn((new User())->setUsername('tester')->setPassword('hash'));
        $tokenGenerator = (new \ReflectionClass(TokenGeneratorService::class))->newInstanceWithoutConstructor();
        $clientDetector = (new \ReflectionClass(ClientDetectorService::class))->newInstanceWithoutConstructor();
        $cookieFactory = (new \ReflectionClass(CookieFactoryService::class))->newInstanceWithoutConstructor();

        $controller = new AuthSelectBranchController(
            $security,
            $this->createMock(BranchRepository::class),
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            (new \ReflectionClass(MercureSubscriberTokenService::class))->newInstanceWithoutConstructor(),
            new TenantContext(),
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create('/api/auth/select-branch', 'POST', [], [], [], [], json_encode([]));
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testHappyPathWebClientSetsMercureCookie(): void
    {
        $jwtSecret = 'test-secret-key-for-unit-tests-at-least-32-chars!!';
        $mercureSecret = 'test-mercure-secret-at-least-32-chars-long!!';

        $role = new Role();
        $user = (new User())->setUsername('tester')->setPassword('hash');
        $user->setRole($role);

        $branch = new Branch();
        $branch->setName('Main Branch');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $branchRepository = $this->createMock(BranchRepository::class);
        $branchRepository->method('find')->willReturn($branch);

        $jwtManager = new JWTManager($jwtSecret, new NullLogger());
        $em = $this->createMock(EntityManagerInterface::class);
        $tenantContext = new TenantContext();
        $tenantContext->setTenant(1, 'acme', null, null);

        $tokenGenerator = new TokenGeneratorService($jwtManager, $em, $tenantContext, 3600, 86400);
        $clientDetector = new ClientDetectorService();
        $cookieFactory = new CookieFactoryService(false);
        $mercureTokenService = new MercureSubscriberTokenService($mercureSecret, 3600);

        $controller = new AuthSelectBranchController(
            $security,
            $branchRepository,
            $tokenGenerator,
            $clientDetector,
            $cookieFactory,
            $mercureTokenService,
            $tenantContext,
            new NullLogger(),
        );

        $request = Request::create(
            '/api/auth/select-branch',
            'POST',
            [],
            [],
            [],
            [],
            json_encode(['branchId' => '1'])
        );

        $response = $controller($request);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $cookieNames = array_map(fn ($c) => $c->getName(), $response->headers->getCookies());
        self::assertContains(JWTAuthenticator::AUTH_COOKIE, $cookieNames);
        self::assertContains(JWTAuthenticator::REFRESH_COOKIE, $cookieNames);
        self::assertContains(JWTAuthenticator::MERCURE_COOKIE, $cookieNames);

        $mercureCookie = array_values(array_filter(
            $response->headers->getCookies(),
            fn ($c) => $c->getName() === JWTAuthenticator::MERCURE_COOKIE
        ))[0];
        self::assertSame('/.well-known/mercure', $mercureCookie->getPath());
    }
}
