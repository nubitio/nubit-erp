<?php

declare(strict_types=1);

namespace App\Tests\AI\Tool;

use App\AI\Tool\CashRegisterTools;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\CashRegister\Repository\CashRegisterRepository;
use App\CashRegister\Repository\CashSessionRepository;
use App\Identity\Entity\User;
use DateTimeImmutable;
use Nubit\Platform\Exception\ServiceException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Regression tests for the 2026-08-19 fix: this tool used to read CashShift
 * (via CashShiftRepository), a table nothing has written to since the
 * CashRegister v2 cutover (see ADR-004's amendment) — meaning it silently
 * returned stale/empty data. It must read CashSession instead.
 */
final class CashRegisterToolsTest extends TestCase
{
    private CashRegisterRepository&MockObject $cashRegisterRepository;
    private CashSessionRepository&MockObject $cashSessionRepository;
    private CashRegisterTools $tool;

    protected function setUp(): void
    {
        $this->cashRegisterRepository = $this->createMock(CashRegisterRepository::class);
        $this->cashSessionRepository = $this->createMock(CashSessionRepository::class);

        $this->tool = new CashRegisterTools($this->cashRegisterRepository, $this->cashSessionRepository);
    }

    public function testThrowsWhenCashRegisterNotFound(): void
    {
        $this->cashRegisterRepository->expects(self::once())->method('find')->with(999)->willReturn(null);
        $this->cashSessionRepository->expects(self::never())->method('findLatestByCashRegister');

        $this->expectException(ServiceException::class);
        $this->tool->cashRegisterStatus(999);
    }

    public function testReturnsClosedWithNoDetailsWhenNoSessionExists(): void
    {
        $this->cashRegisterRepository
            ->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($this->makeCashRegister(1, 'Caja Principal'));
        $this->cashSessionRepository
            ->expects(self::once())
            ->method('findLatestByCashRegister')
            ->with(1)
            ->willReturn(null);

        $result = $this->tool->cashRegisterStatus(1);

        self::assertSame(1, $result->cashRegisterId);
        self::assertSame('Caja Principal', $result->cashRegisterName);
        self::assertSame('CLOSED', $result->status);
        self::assertNull($result->shiftId);
        self::assertNull($result->openedAt);
        self::assertNull($result->openingBalance);
    }

    public function testReturnsLatestCashSessionDetails(): void
    {
        $this->cashRegisterRepository
            ->expects(self::once())
            ->method('find')
            ->with(1)
            ->willReturn($this->makeCashRegister(1, 'Caja Principal'));

        $user = new User()->setUsername('cajera1');
        new ReflectionProperty(User::class, 'id')->setValue($user, 7);

        $session = new CashSession()
            ->setStatus('OPEN')
            ->setOpeningAmount('100.00')
            ->setOpenedAt(new DateTimeImmutable('2026-08-19T08:00:00+00:00'))
            ->setOpenedBy($user);
        new ReflectionProperty(CashSession::class, 'id')->setValue($session, 42);

        $this->cashSessionRepository
            ->expects(self::once())
            ->method('findLatestByCashRegister')
            ->with(1)
            ->willReturn($session);

        $result = $this->tool->cashRegisterStatus(1);

        self::assertSame('OPEN', $result->status);
        self::assertSame(42, $result->shiftId);
        self::assertSame('2026-08-19T08:00:00+00:00', $result->openedAt);
        self::assertSame('cajera1', $result->openedBy);
        self::assertSame(100.0, $result->openingBalance);
        self::assertNull($result->closedAt);
    }

    private function makeCashRegister(int $id, string $name): CashRegister
    {
        $register = new CashRegister();
        $register->setName($name);
        new ReflectionProperty(CashRegister::class, 'id')->setValue($register, $id);

        return $register;
    }
}
