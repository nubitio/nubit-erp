<?php

declare(strict_types=1);

namespace App\Tests\CashRegister\State;

use ApiPlatform\Metadata\Put;
use App\CashRegister\Dto\CashRegisterCloseDto;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\State\CashRegisterCloseProcessor;
use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\User;
use App\Identity\Security\Security;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
final class CashRegisterCloseProcessorTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $entityManager;

    /** @var Security&MockObject */
    private Security $security;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->security = $this->createMock(Security::class);
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(
            fn (\Closure $operation) => $operation($this->connection),
        );
        $this->entityManager->method('getConnection')->willReturn($this->connection);

        $authUser = new AuthUserDto(userId: 1, username: 'cajero');
        $this->security->method('connectedUser')->willReturn($authUser);
    }

    /**
     * TC-3: Closure items that would produce rounding errors with float (0.1 + 0.2)
     * must be accumulated with exact BigDecimal arithmetic.
     */
    public function testClosureItemsAccumulatedWithBrickMath(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 1);
        $cashSession->setOpenedBy($openedBy);
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(fn() => null);
        $this->connection->method('fetchOne')->willReturn('0.20');

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
        $this->assertSame('0.20', $cashSession->getExpectedAmount());
        $this->assertSame('0.30', $cashSession->getCountedAmount());
        $this->assertSame('0.10', $cashSession->getDifferenceAmount());
        $this->assertTrue($response->success);
    }

    /** What the closing records besides the totals: the count by denomination and where the cash goes. */
    public function testTheClosingKeepsTheDenominationCountAndTheCashTakenOut(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 1);
        $cashSession->setOpenedBy($openedBy);
        $cashSession->setOpeningAmount('200.00');
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(fn() => null);
        $this->connection->method('fetchOne')->willReturn('1645.30');

        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [[
            'paymentMethod' => '/api/payment-methods/1',
            'countedAmount' => '1840.30',
            'note' => '',
        ]];
        // 8 x S/ 200, 2 x S/ 100... and a denomination the till does not have, which is dropped.
        $dto->denominations = ['20000' => 8, '10000' => 2, '10' => 3, '777' => 4, '5000' => 0];
        $dto->fundKept = true;

        $this->buildProcessor()->process($dto, new Put(), ['id' => 1]);

        $this->assertSame(['20000' => 8, '10000' => 2, '10' => 3], $cashSession->getCountedDenominations());
        $this->assertTrue($cashSession->getOpeningFundKept());
        $this->assertSame('1640.30', $cashSession->getCashWithdrawnAtClose(), 'counted less the fund that stays');
    }

    public function testWhenTheFundDoesNotStayAllTheCountedCashIsTakenOut(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 1);
        $cashSession->setOpenedBy($openedBy);
        $cashSession->setOpeningAmount('200.00');
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(fn() => null);
        $this->connection->method('fetchOne')->willReturn('100.00');

        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [['paymentMethod' => '/api/payment-methods/1', 'countedAmount' => '300.00', 'note' => '']];
        $dto->fundKept = false;

        $this->buildProcessor()->process($dto, new Put(), ['id' => 1]);

        $this->assertNull($cashSession->getCountedDenominations());
        $this->assertSame('300.00', $cashSession->getCashWithdrawnAtClose());
    }

    /**
     * Yape, a card and the like never enter the drawer: what the cashier checks against the terminal must not be
     * added to the cash count, or every digital sale would show up as a surplus.
     */
    public function testOnlyTheCashItemIsComparedWithTheDrawer(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 1);
        $cashSession->setOpenedBy($openedBy);
        $cashSession->setOpeningAmount('100.00');
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(fn() => null);
        $this->connection->method('fetchOne')->willReturn('37.50');

        $dto = new CashRegisterCloseDto();
        $dto->closureItems = [
            ['paymentMethod' => '/api/payment-methods/1', 'countedAmount' => '137.50', 'note' => ''],
            ['paymentMethod' => '/api/payment-methods/7', 'countedAmount' => '30.60', 'note' => 'Yape verificado'],
        ];

        $response = $this->buildProcessor()->process($dto, new Put(), ['id' => 1]);

        $this->assertSame('137.50', $cashSession->getExpectedAmount());
        $this->assertSame('137.50', $cashSession->getCountedAmount());
        $this->assertSame('0.00', $cashSession->getDifferenceAmount());
        $this->assertSame('Yape verificado', $cashSession->getCloseNote());
        $this->assertTrue($response->success);
    }

    /**
     * TC-4: Direct expectedAmount/countedAmount strings must produce an exact difference.
     */
    public function testSingleAmountPathUsesExactScale(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 1);
        $cashSession->setOpenedBy($openedBy);
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->entityManager->method('getReference')->willReturn(new User());
        $this->entityManager->method('persist')->willReturnCallback(fn() => null);
        $this->entityManager->method('flush')->willReturnCallback(fn() => null);
        $this->connection->method('fetchOne')->willReturn('123.456');

        $dto = new CashRegisterCloseDto();
        $dto->expectedAmount = '123.456';
        $dto->countedAmount = '100.004';
        // difference = 100.004 - 123.456 = -23.452 → rounded half-up to -23.45

        $processor = $this->buildProcessor();
        $processor->process($dto, new Put(), ['id' => 2]);

        $this->assertSame('123.46', $cashSession->getExpectedAmount());
        $this->assertSame('100.00', $cashSession->getCountedAmount());
        $this->assertSame('-23.45', $cashSession->getDifferenceAmount());
    }

    public function testCannotCloseAnotherUsersSessionWithoutPermission(): void
    {
        $cashSession = new CashSession();
        $openedBy = new User();
        new \ReflectionProperty(User::class, 'id')->setValue($openedBy, 99);
        $cashSession->setOpenedBy($openedBy);
        $this->entityManager->method('find')->willReturn($cashSession);
        $this->security->method('hasPermission')->willReturn(false);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class);

        $dto = new CashRegisterCloseDto();
        $dto->countedAmount = '10.00';
        $this->buildProcessor()->process($dto, new Put(), ['id' => 3]);
    }

    public function testClosingLocksTheSessionAndDoesNotRecalculateAnAlreadyClosedCut(): void
    {
        $cashSession = new CashSession()->setStatus('CLOSED');
        $this->connection->expects(self::once())->method('transactional');
        $this->entityManager->expects(self::once())
            ->method('find')
            ->with(CashSession::class, 5, LockMode::PESSIMISTIC_WRITE)
            ->willReturn($cashSession);
        $this->connection->expects(self::never())->method('fetchOne');
        $this->entityManager->expects(self::never())->method('flush');

        $dto = new CashRegisterCloseDto();
        $dto->countedAmount = '10.00';
        $this->expectException(ConflictHttpException::class);
        $this->buildProcessor()->process($dto, new Put(), ['id' => 5]);
    }

    private function buildProcessor(): CashRegisterCloseProcessor
    {
        return new CashRegisterCloseProcessor(entityManager: $this->entityManager, security: $this->security);
    }
}
