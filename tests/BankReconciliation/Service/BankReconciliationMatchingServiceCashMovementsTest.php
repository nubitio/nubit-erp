<?php

declare(strict_types=1);

namespace App\Tests\BankReconciliation\Service;

use App\BankReconciliation\Service\BankReconciliationMatchingService;
use App\CashRegister\Entity\CashMovement;
use App\CashRegister\Enum\CashMovementType;
use App\CashRegister\Service\CashMovementHydrator;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the 2026-08-19 fix: unreconciledCashMovements() used
 * to query the ORM `CashMovement` entity, which maps to the frozen legacy
 * `cash_movement` (singular) table — nothing has written to it since the
 * CashRegister v2 cutover, so this returned zero/stale candidates for any
 * bank statement line dated after that cutover. It must read the v2
 * `cash_movements` (plural) table instead. See ADR-004's amendment.
 */
#[AllowMockObjectsWithoutExpectations]
final class BankReconciliationMatchingServiceCashMovementsTest extends TestCase
{
    public function testUnreconciledCashMovementsReadsFromV2TableAndHydratesRows(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('FROM cash_movements cm'))
            ->willReturn([
                [
                    'id' => 501,
                    'cash_session_id' => null,
                    'movement_type' => 'CASH_IN',
                    'category_id' => null,
                    'amount' => '75.00',
                    'description' => 'Venta post-cutover',
                    'created_at' => '2026-08-15 10:00:00',
                ],
            ]);

        $em = $this->createStub(EntityManagerInterface::class);
        $hydrator = new CashMovementHydrator($em, $connection);

        $service = new BankReconciliationMatchingService($em, $connection, $hydrator);

        $result = $service->unreconciledCashMovements(
            new DateTimeImmutable('2026-08-01'),
            new DateTimeImmutable('2026-08-31'),
        );

        self::assertCount(1, $result);
        self::assertInstanceOf(CashMovement::class, $result[0]);
        self::assertSame(501, $result[0]->getId());
        self::assertSame('75.00', $result[0]->getAmount());
        self::assertSame(CashMovementType::INCOME, $result[0]->getType());
    }

    public function testUnreconciledCashMovementsExcludesAlreadyMatchedViaLeftJoin(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(self::stringContains('LEFT JOIN bank_reconciliation_match m ON m.cash_movement_id = cm.id'))
            ->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $hydrator = new CashMovementHydrator($em, $connection);

        $service = new BankReconciliationMatchingService($em, $connection, $hydrator);

        $result = $service->unreconciledCashMovements(null, null);

        self::assertSame([], $result);
    }
}
