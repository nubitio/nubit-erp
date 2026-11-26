<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Post;
use App\CashRegister\Dto\CashRegisterOpenDto;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\State\CashRegisterOpenProcessor;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CashRegisterOpenProcessorTest extends TestCase
{
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

        $this->entityManager->method('getConnection')->willReturn($this->connection);
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
                    default => new $class(),
                };
            });

        $branch = new Branch();
        $this->setBranchId($branch, 'branch-1');
        $this->branchRepository->method('find')->willReturn($branch);

        $cashRegister = new CashRegister()->setBranch($branch);
        $this->entityManager->method('find')->with(CashRegister::class, 1)->willReturn($cashRegister);

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

        $this->entityManager->method('persist')->willReturnCallback(static fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(static fn() => null);
        $this->entityManager->method('getReference')->willReturn(new User());

        $branch = new Branch();
        $this->setBranchId($branch, 'branch-42');
        $this->branchRepository->method('find')->willReturn($branch);

        $cashRegister = new CashRegister()->setBranch($branch);
        $this->entityManager->method('find')->with(CashRegister::class, 1)->willReturn($cashRegister);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->connection->method('isTransactionActive')->willReturn(true);

        // Build UniqueConstraintViolationException using a mock Driver\Exception
        $driverEx = new class('Duplicate entry', '23000', 1062) extends \Exception implements
            \Doctrine\DBAL\Driver\Exception {
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

        $this->connection->method('executeStatement')->willThrowException($uniqueViolation);

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

    public function testDoesNotRollbackWhenTransactionIsAlreadyInactive(): void
    {
        $authUser = new AuthUserDto(userId: 3, username: 'cajero3');
        $this->security->method('connectedUser')->willReturn($authUser);
        $this->entityManager->method('persist')->willReturnCallback(static fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(static fn() => null);
        $this->entityManager->method('getReference')->willReturn(new User());

        $branch = new Branch();
        $this->setBranchId($branch, 'branch-3');
        $this->branchRepository->method('find')->willReturn($branch);
        $cashRegister = new CashRegister()->setBranch($branch);
        $this->entityManager->method('find')->with(CashRegister::class, 1)->willReturn($cashRegister);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection
            ->method('executeStatement')
            ->willThrowException(new \RuntimeException('Original database error'));
        $this->connection->method('isTransactionActive')->willReturn(false);
        $this->connection->expects($this->never())->method('rollBack');

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            branchContextService: $this->buildBranchContextWithFixedId('branch-3'),
            branchRepository: $this->branchRepository,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Original database error');

        $processor->process(new CashRegisterOpenDto(id: 1, openingBalance: '100'), new Post());
    }

    public function testOriginalExceptionIsPreservedWhenRollbackFindsNoPhysicalTransaction(): void
    {
        $authUser = new AuthUserDto(userId: 4, username: 'cajero4');
        $this->security->method('connectedUser')->willReturn($authUser);
        $this->entityManager->method('persist')->willReturnCallback(static fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(static fn() => null);
        $this->entityManager->method('getReference')->willReturn(new User());

        $branch = new Branch();
        $this->setBranchId($branch, 'branch-4');
        $this->branchRepository->method('find')->willReturn($branch);
        $cashRegister = new CashRegister()->setBranch($branch);
        $this->entityManager->method('find')->with(CashRegister::class, 1)->willReturn($cashRegister);

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection
            ->method('executeStatement')
            ->willThrowException(new \RuntimeException('Original insert failure'));
        $this->connection->method('isTransactionActive')->willReturn(true);
        $this->connection
            ->expects($this->once())
            ->method('rollBack')
            ->willThrowException(new \RuntimeException('There is no active transaction'));

        $processor = new CashRegisterOpenProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
            branchContextService: $this->buildBranchContextWithFixedId('branch-4'),
            branchRepository: $this->branchRepository,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Original insert failure');

        $processor->process(new CashRegisterOpenDto(id: 1, openingBalance: '100'), new Post());
    }

    private function buildBranchContextWithFixedId(string $branchId): BranchContextServiceInterface
    {
        $context = $this->createStub(BranchContextServiceInterface::class);
        $context->method('getCurrentBranchId')->willReturn($branchId);

        return $context;
    }

    private function setBranchId(Branch $branch, string $id): void
    {
        $reflection = new \ReflectionClass(Branch::class);
        $reflection->getProperty('id')->setValue($branch, $id);
    }
}
