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
use App\Organization\Entity\Branch;
use App\Entity\CashSession;
use App\Identity\Entity\User;
use App\Organization\Repository\BranchRepository;
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

    /** @var BranchRepository&MockObject */
    private BranchRepository $branchRepository;

    /** @var Connection&MockObject */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->branchRepository = $this->createMock(BranchRepository::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);
    }

    /**
     * TC-1: Opening balance "150" must be stored as "150.00" (exact 2-decimal scale)
     * and the processor must return a CashSession.
     */
    public function testOpeningBalanceStoredWithExactScale(): void
    {
        $authUser = new AuthUserDto(userId: 1, username: 'cajero');
        $this->security->method('connectedUser')->willReturn($authUser);

        /** @var CashSession|null $capturedSession */
        $capturedSession = null;
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$capturedSession): void {
                if ($entity instanceof CashSession) {
                    $capturedSession = $entity;
                }
            });

        $this->entityManager->expects($this->once())->method('flush');

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('commit');
        $this->connection->expects($this->never())->method('rollBack');
        $this->connection->method('executeStatement')->willReturn(1);

        $this->entityManager
            ->method('getReference')
            ->willReturnCallback(static function (string $class, mixed $id): object {
                return match ($class) {
                    User::class => new User(),
                    default     => new $class(),
                };
            });

        $branch = new Branch();
        $this->branchRepository->method('find')->willReturn($branch);

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            branchContextService: $this->buildBranchContextWithFixedId('branch-1'),
            branchRepository: $this->branchRepository,
        );

        $dto = new CashRegisterOpenDto(id: 1, openingBalance: '150');
        $result = $processor->process($dto, new Post());

        $this->assertInstanceOf(CashSession::class, $result);
        $this->assertInstanceOf(CashSession::class, $capturedSession, 'CashSession was not persisted');
        /** @var CashSession $capturedSession */
        $this->assertSame('150.00', $capturedSession->getOpeningAmount());
    }

    /**
     * TC-2: When the DBAL insert raises UniqueConstraintViolationException,
     * a ConflictHttpException must be thrown with the correct message.
     */
    public function testDuplicateOpenThrowsConflictException(): void
    {
        $authUser = new AuthUserDto(userId: 2, username: 'cajero2');
        $this->security->method('connectedUser')->willReturn($authUser);

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

        $branch = new Branch();
        $this->branchRepository->method('find')->willReturn($branch);

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
            ->method('executeStatement')
            ->willThrowException($uniqueViolation);

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            branchContextService: $this->buildBranchContextWithFixedId('branch-42'),
            branchRepository: $this->branchRepository,
        );

        $dto = new CashRegisterOpenDto(id: 1, openingBalance: '200.00');

        $this->expectException(ConflictHttpException::class);
        $this->expectExceptionMessage('Esta caja ya tiene un turno abierto.');

        $processor->process($dto, new Post());
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
