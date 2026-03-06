<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\Post;
use App\Core\Application\Session\Security;
use App\Core\Security\Auth\BranchContextService;
use App\Core\Security\Auth\JWTAuthenticationToken;
use App\Core\Security\Auth\JWTManager;
use App\Dto\Auth\AuthUserDto;
use App\Dto\CashRegister\CashRegisterOpenDto;
use App\Entity\CashMovementCategory;
use App\Entity\CashShift;
use App\Entity\User;
use App\Enum\CashMovementType;
use App\Repository\BranchRepository;
use App\Repository\CashMovementCategoryRepository;
use App\State\CashRegister\CashRegisterOpenProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\SecurityBundle\Security as SymfonySecurity;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class CashRegisterOpenProcessorTest extends TestCase
{
    private const string JWT_TEST_SECRET = 'test-secret-at-least-32-chars-long-ok';

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var Security&MockObject */
    private Security $security;

    /** @var CashMovementCategoryRepository&MockObject */
    private CashMovementCategoryRepository $categoryRepository;

    /** @var BranchRepository&MockObject */
    private BranchRepository $branchRepository;

    /** @var Connection&MockObject */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->categoryRepository = $this->createMock(CashMovementCategoryRepository::class);
        $this->branchRepository = $this->createMock(BranchRepository::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);
    }

    /**
     * TC-1: Opening balance "150" must be stored as "150.00" (exact 2-decimal scale).
     */
    public function testOpeningBalanceStoredWithExactScale(): void
    {
        $authUser = new AuthUserDto(userId: 1, username: 'cajero');
        $this->security->method('connectedUser')->willReturn($authUser);

        $category = $this->createMock(CashMovementCategory::class);
        $category->method('getType')->willReturn(CashMovementType::INCOME);
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        /** @var CashShift|null $capturedShift */
        $capturedShift = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$capturedShift): void {
                if ($entity instanceof CashShift) {
                    $capturedShift = $entity;
                }
            });

        $this->entityManager->expects($this->once())->method('flush');

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');

        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(static function (string $class, mixed $id): object {
                return match ($class) {
                    User::class => new User(),
                    default     => new $class(),
                };
            });

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            cashMovementCategoryRepository: $this->categoryRepository,
            branchContextService: $this->buildBranchContextWithNullBranch(),
            branchRepository: $this->branchRepository,
        );

        $dto = new CashRegisterOpenDto(id: 1, openingBalance: '150');
        $result = $processor->process($dto, new Post());

        $this->assertInstanceOf(CashShift::class, $result);
        $this->assertInstanceOf(CashShift::class, $capturedShift, 'CashShift was not persisted');
        /** @var CashShift $capturedShift */
        $this->assertSame('150.00', $capturedShift->getOpeningBalance());
    }

    /**
     * TC-2: When the DBAL insert raises UniqueConstraintViolationException,
     * a ConflictHttpException must be thrown with the correct message.
     */
    public function testDuplicateOpenThrowsConflictException(): void
    {
        $authUser = new AuthUserDto(userId: 2, username: 'cajero2');
        $this->security->method('connectedUser')->willReturn($authUser);

        $category = $this->createMock(CashMovementCategory::class);
        $category->method('getType')->willReturn(CashMovementType::INCOME);
        $this->categoryRepository->method('findOneBy')->willReturn($category);

        $this->entityManager->method('persist')->willReturnCallback(fn () => null);
        $this->entityManager->method('flush')->willReturnCallback(fn () => null);
        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(static function (string $class, mixed $id): object {
                return match ($class) {
                    User::class => new User(),
                    default     => new $class(),
                };
            });

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');

        // Build UniqueConstraintViolationException using a mock Driver\Exception
        $driverEx = new class ('Duplicate entry', '23000', 1062) extends \Exception implements \Doctrine\DBAL\Driver\Exception {
            public function __construct(
                string $message,
                private readonly string $sqlState,
                int $code,
            ) {
                parent::__construct($message, $code);
            }

            public function getSQLState(): string
            {
                return $this->sqlState;
            }
        };
        $uniqueViolation = new UniqueConstraintViolationException($driverEx, null);

        $this->connection
            ->method('insert')
            ->willThrowException($uniqueViolation);

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            cashMovementCategoryRepository: $this->categoryRepository,
            branchContextService: $this->buildBranchContextWithFixedId('branch-42'),
            branchRepository: $this->branchRepository,
        );

        $dto = new CashRegisterOpenDto(id: 1, openingBalance: '200.00');

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Esta caja ya tiene un turno abierto.');

        $processor->process($dto, new Post());
    }

    /**
     * Build a BranchContextService stub (final readonly) that returns null for getCurrentBranchId().
     * Achieved by injecting a Symfony Security that returns no token.
     */
    private function buildBranchContextWithNullBranch(): BranchContextService
    {
        $reflection = new \ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $reflection->newInstanceWithoutConstructor();

        /** @var SymfonySecurity&MockObject $securityMock */
        $securityMock = $this->createMock(SymfonySecurity::class);
        $securityMock->method('getToken')->willReturn(null);

        // JWTManager is also final — use newInstanceWithoutConstructor + property injection
        $jwtReflection = new \ReflectionClass(JWTManager::class);
        /** @var JWTManager $jwtStub */
        $jwtStub = $jwtReflection->newInstanceWithoutConstructor();
        $jwtReflection->getProperty('secret')->setValue($jwtStub, self::JWT_TEST_SECRET);
        $jwtReflection->getProperty('logger')->setValue($jwtStub, new NullLogger());

        $reflection->getProperty('security')->setValue($stub, $securityMock);
        $reflection->getProperty('jwtManager')->setValue($stub, $jwtStub);

        return $stub;
    }

    /**
     * Build a BranchContextService stub (final readonly) that returns a fixed branch ID.
     * Uses a real JWT signed with the test secret and decoded by a real JWTManager stub.
     */
    private function buildBranchContextWithFixedId(string $branchId): BranchContextService
    {
        $reflection = new \ReflectionClass(BranchContextService::class);
        /** @var BranchContextService $stub */
        $stub = $reflection->newInstanceWithoutConstructor();

        // Build a real JWTManager stub (final) via reflection
        $jwtReflection = new \ReflectionClass(JWTManager::class);
        /** @var JWTManager $jwtManager */
        $jwtManager = $jwtReflection->newInstanceWithoutConstructor();
        $jwtReflection->getProperty('secret')->setValue($jwtManager, self::JWT_TEST_SECRET);
        $jwtReflection->getProperty('logger')->setValue($jwtManager, new NullLogger());

        // Encode a real JWT with the branchId claim
        $rawJwt = JWT::encode(['branchId' => $branchId], self::JWT_TEST_SECRET, 'HS256');

        // Build a real JWTAuthenticationToken (not final)
        $user = $this->createMock(User::class);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $token = new JWTAuthenticationToken($user, 'main', ['ROLE_USER'], $rawJwt);

        /** @var SymfonySecurity&MockObject $securityMock */
        $securityMock = $this->createMock(SymfonySecurity::class);
        $securityMock->method('getToken')->willReturn($token);

        $reflection->getProperty('security')->setValue($stub, $securityMock);
        $reflection->getProperty('jwtManager')->setValue($stub, $jwtManager);

        return $stub;
    }
}
