<?php

declare(strict_types=1);

namespace App\Tests\State\CashRegister;

use ApiPlatform\Metadata\Put;
use App\Core\Application\Session\Security;
use App\Dto\Auth\AuthUserDto;
use App\Dto\CashRegister\CashRegisterCloseDto;
use App\Entity\CashShift;
use App\Entity\User;
use App\State\CashRegister\CashRegisterCloseProcessor;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CashRegisterCloseProcessorTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var Security&MockObject */
    private Security $security;

    /** @var Connection&MockObject */
    private Connection $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager
            ->method('getConnection')
            ->willReturn($this->connection);

        $authUser = new AuthUserDto(userId: 1, username: 'cajero');
        $this->security->method('connectedUser')->willReturn($authUser);

        $this->connection->method('beginTransaction');
        $this->connection->method('commit');
        $this->connection->method('executeStatement')->willReturn(0);
    }

    /**
     * TC-3: Closure items that would produce rounding errors with float (0.1 + 0.2)
     * must be accumulated with exact BigDecimal arithmetic.
     */
    public function testClosureItemsAccumulatedWithBrickMath(): void
    {
        $cashShift = $this->buildCashShift();
        $this->entityManager->method('find')->willReturn($cashShift);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn () => null);
        $this->entityManager->method('flush')->willReturnCallback(fn () => null);

        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [
            ['expectedAmount' => '0.1', 'countedAmount' => '0.2'],
            ['expectedAmount' => '0.1', 'countedAmount' => '0.1'],
        ];

        $processor = $this->buildProcessor();
        $response = $processor->process($dto, new Put(), ['id' => 1]);

        // expectedTotal = 0.1 + 0.1 = 0.20 (exact)
        // countedTotal  = 0.2 + 0.1 = 0.30 (exact — would be 0.30000000000000004 with float)
        // difference    = 0.30 - 0.20 = 0.10
        $this->assertSame('0.20', $cashShift->getExpectedAmount());
        $this->assertSame('0.30', $cashShift->getCountedAmount());
        $this->assertSame('0.10', $cashShift->getDifference());
        $this->assertTrue($response->success);
    }

    /**
     * TC-4: Direct expectedAmount/countedAmount strings must produce an exact difference.
     */
    public function testSingleAmountPathUsesExactScale(): void
    {
        $cashShift = $this->buildCashShift();
        $this->entityManager->method('find')->willReturn($cashShift);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn () => null);
        $this->entityManager->method('flush')->willReturnCallback(fn () => null);

        $dto = new CashRegisterCloseDto();
        $dto->expectedAmount = '123.456';
        $dto->countedAmount = '100.004';
        // difference = 100.004 - 123.456 = -23.452 → rounded half-up to -23.45

        $processor = $this->buildProcessor();
        $processor->process($dto, new Put(), ['id' => 2]);

        $this->assertSame('123.46', $cashShift->getExpectedAmount());
        $this->assertSame('100.00', $cashShift->getCountedAmount());
        $this->assertSame('-23.45', $cashShift->getDifference());
    }

    private function buildCashShift(): CashShift
    {
        return new CashShift();
    }

    private function buildProcessor(): CashRegisterCloseProcessor
    {
        return new CashRegisterCloseProcessor(
            entityManager: $this->entityManager,
            security: $this->security,
        );
    }
}
