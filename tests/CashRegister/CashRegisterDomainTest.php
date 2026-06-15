<?php

declare(strict_types=1);

namespace App\Tests\CashRegister;

use App\CashRegister\Dto\CashRegisterCloseDto;
use App\CashRegister\Dto\CashRegisterDashboardDto;
use App\CashRegister\Dto\CashRegisterOpenDto;
use App\CashRegister\Dto\CashSessionClosureSummaryDto;
use App\CashRegister\Dto\CashSessionPaymentMethodSalesDto;
use App\CashRegister\Dto\CashSessionXReportDto;
use App\CashRegister\Entity\CashRegister;
use App\CashRegister\Entity\CashSession;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Pure value-object and entity tests for the CashRegister bounded context.
 */
final class CashRegisterDomainTest extends TestCase
{
    // ── DTOs ──────────────────────────────────────────────────────────────────

    public function testCashRegisterDashboardDtoHoldsFields(): void
    {
        $openedAt = new DateTimeImmutable('2026-04-30 08:00:00');
        $dto = new CashRegisterDashboardDto(
            id: 1,
            name: 'Caja Principal',
            cashSessionId: 42,
            cashShiftId: 42,
            openedAt: $openedAt,
            closedAt: null,
            cashier: 'Juan',
            balance: 500.00,
            status: 'OPEN',
            todaySales: '1250.00',
        );

        self::assertSame(1, $dto->id);
        self::assertSame('Caja Principal', $dto->name);
        self::assertSame(42, $dto->cashShiftId);
        self::assertSame($openedAt, $dto->openedAt);
        self::assertNull($dto->closedAt);
        self::assertSame('Juan', $dto->cashier);
        self::assertEqualsWithDelta(500.0, $dto->balance ?? 0, 0.001);
        self::assertSame('OPEN', $dto->status);
        self::assertSame('1250.00', $dto->todaySales);
    }

    public function testCashRegisterDashboardDtoDefaultTodaySales(): void
    {
        $dto = new CashRegisterDashboardDto(
            id: 2,
            name: 'Caja 2',
            cashSessionId: null,
            cashShiftId: null,
            openedAt: null,
            closedAt: null,
            cashier: null,
            balance: null,
            status: null,
        );

        self::assertSame('0.00', $dto->todaySales);
    }

    public function testCashSessionXReportDtoHoldsFields(): void
    {
        $salesByPM = [
            new CashSessionPaymentMethodSalesDto(1, 'Efectivo', '800.00'),
        ];
        $dto = new CashSessionXReportDto(
            openingBalance: '200.00',
            totalSales: '1000.00',
            cashIn: '50.00',
            cashOut: '30.00',
            currentBalance: '1220.00',
            shiftDurationMinutes: 480,
            salesByPaymentMethod: $salesByPM,
        );

        self::assertSame('200.00', $dto->openingBalance);
        self::assertSame('1000.00', $dto->totalSales);
        self::assertSame('50.00', $dto->cashIn);
        self::assertSame('30.00', $dto->cashOut);
        self::assertSame('1220.00', $dto->currentBalance);
        self::assertSame(480, $dto->shiftDurationMinutes);
        self::assertCount(1, $dto->salesByPaymentMethod);
        self::assertSame('Efectivo', $dto->salesByPaymentMethod[0]->paymentMethod);
    }

    public function testCashRegisterCloseDtoDefaults(): void
    {
        $dto = new CashRegisterCloseDto();

        self::assertNull($dto->expectedAmount);
        self::assertNull($dto->countedAmount);
        self::assertNull($dto->difference);
        self::assertNull($dto->note);
        self::assertSame([], $dto->closureItems);
    }

    public function testCashRegisterCloseDtoIsWritable(): void
    {
        $dto = new CashRegisterCloseDto();
        $dto->expectedAmount = '1500.00';
        $dto->countedAmount = '1498.50';
        $dto->difference = '-1.50';
        $dto->note = 'Pequeña diferencia';

        self::assertSame('1500.00', $dto->expectedAmount);
        self::assertSame('1498.50', $dto->countedAmount);
        self::assertSame('-1.50', $dto->difference);
    }

    public function testCashSessionClosureSummaryDtoHoldsFields(): void
    {
        $dto = new CashSessionClosureSummaryDto(
            paymentMethodId: 1,
            paymentMethod: 'Efectivo',
            expectedAmount: '1500.00',
        );

        self::assertSame(1, $dto->paymentMethodId);
        self::assertSame('Efectivo', $dto->paymentMethod);
        self::assertSame('1500.00', $dto->expectedAmount);
    }

    public function testCashRegisterOpenDtoHoldsFields(): void
    {
        $dto = new CashRegisterOpenDto(id: 3, openingBalance: '200.00');

        self::assertSame(3, $dto->id);
        self::assertSame('200.00', $dto->openingBalance);
    }

    public function testCashRegisterOpenDtoAcceptsFloatBalance(): void
    {
        $dto = new CashRegisterOpenDto(id: 1, openingBalance: 250.50);

        self::assertSame(250.50, $dto->openingBalance);
    }

    // ── CashRegister entity ───────────────────────────────────────────────────

    public function testCashRegisterSettersAndGetters(): void
    {
        $branch = new Branch();
        $cr = new CashRegister();
        $cr->setBranch($branch);
        $cr->setName('Caja Principal');

        self::assertSame('Caja Principal', $cr->getName());
        self::assertSame($branch, $cr->getBranch());
        self::assertNull($cr->getId());
        self::assertCount(0, $cr->getCashSessions());
    }

    public function testCashRegisterFluentSetters(): void
    {
        $branch = new Branch();
        $cr = new CashRegister();

        self::assertSame($cr, $cr->setBranch($branch));
        self::assertSame($cr, $cr->setName('Caja'));
    }

    // ── CashSession entity ────────────────────────────────────────────────────

    private function makeUser(): User
    {
        $user = new User();
        $user->setUsername('cashier');
        $role = new Role();
        $idRef = new \ReflectionProperty(User::class, 'id');
        $idRef->setValue($user, 1);
        $user->setRole($role);

        return $user;
    }

    public function testCashSessionDefaultValues(): void
    {
        $session = new CashSession();

        self::assertNull($session->getId());
        self::assertSame('OPEN', $session->getStatus());
        self::assertSame('0.00', $session->getOpeningAmount());
        self::assertNull($session->getExpectedAmount());
        self::assertNull($session->getCountedAmount());
        self::assertNull($session->getDifferenceAmount());
        self::assertNull($session->getClosedAt());
        self::assertNull($session->getCloseNote());
        self::assertNull($session->getClosedBy());
        self::assertNull($session->getUpdatedAt());
    }

    public function testCashSessionSettersAndGetters(): void
    {
        $cashRegister = new CashRegister();
        $cashRegister->setName('Caja 1');
        $branch = new Branch();
        $user = $this->makeUser();
        $openedAt = new DateTimeImmutable('2026-04-30 08:00:00');

        $session = new CashSession();
        $session->setCashRegister($cashRegister);
        $session->setBranch($branch);
        $session->setOpenedBy($user);
        $session->setStatus('OPEN');
        $session->setOpeningAmount('200.00');
        $session->setOpenedAt($openedAt);

        self::assertSame($cashRegister, $session->getCashRegister());
        self::assertSame($branch, $session->getBranch());
        self::assertSame($user, $session->getOpenedBy());
        self::assertSame('OPEN', $session->getStatus());
        self::assertSame('200.00', $session->getOpeningAmount());
        self::assertSame($openedAt, $session->getOpenedAt());
    }

    public function testCashSessionCloseFlow(): void
    {
        $session = new CashSession();
        $closer = $this->makeUser();
        $closedAt = new DateTimeImmutable('2026-04-30 18:00:00');

        $session->setStatus('CLOSED');
        $session->setClosedBy($closer);
        $session->setClosedAt($closedAt);
        $session->setExpectedAmount('1500.00');
        $session->setCountedAmount('1498.50');
        $session->setDifferenceAmount('-1.50');
        $session->setCloseNote('Cierre de turno');

        self::assertSame('CLOSED', $session->getStatus());
        self::assertSame($closer, $session->getClosedBy());
        self::assertSame($closedAt, $session->getClosedAt());
        self::assertSame('1500.00', $session->getExpectedAmount());
        self::assertSame('1498.50', $session->getCountedAmount());
        self::assertSame('-1.50', $session->getDifferenceAmount());
        self::assertSame('Cierre de turno', $session->getCloseNote());
    }

    public function testCashSessionCreatedAtCanBeSet(): void
    {
        $createdAt = new DateTimeImmutable('2026-04-30 08:00:00');
        $session = new CashSession();
        $session->setCreatedAt($createdAt);

        self::assertSame($createdAt, $session->getCreatedAt());
    }
}
