<?php

declare(strict_types=1);

namespace App\Tests\Identity\Controller;

use App\Identity\Auth\SelectedBranchContext;
use App\Identity\Controller\AuthSelectBranchController;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
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
}
