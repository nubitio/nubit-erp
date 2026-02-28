<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\AuthSelectBranchController;
use App\Core\Security\Auth\ClientDetectorService;
use App\Core\Security\Auth\CookieFactoryService;
use App\Core\Security\Auth\TokenGeneratorService;
use App\Entity\User;
use App\Repository\BranchRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
            $this->createMock(LoggerInterface::class),
        );

        $request = Request::create('/api/auth/select-branch', 'POST', [], [], [], [], json_encode([]));
        $response = $controller($request);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }
}
