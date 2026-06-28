<?php

declare(strict_types=1);

namespace App\Tests\Unit\CashRegister;

use App\CashRegister\Domain\Enum\CashSessionStatus;
use App\CashRegister\Domain\Enum\MovementType;
use App\CashRegister\Domain\Event\CashMovementRegistered;
use App\CashRegister\Domain\Event\CashSessionClosed;
use App\CashRegister\Domain\Event\CashSessionOpened;
use App\CashRegister\Domain\Exception\CashSessionAlreadyOpenException;
use App\CashRegister\Domain\Exception\CashSessionNotOpenException;
use App\CashRegister\Domain\Model\CashReconciliation;
use App\CashRegister\Domain\Model\CashSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Pure domain invariants for CashSession — no kernel boot, no Doctrine, no DB.
 *
 * @see CashSession
 */
#[CoversClass(CashSession::class)]
#[CoversClass(CashSessionOpened::class)]
#[CoversClass(CashMovementRegistered::class)]
#[CoversClass(CashSessionClosed::class)]
#[CoversClass(CashReconciliation::class)]
#[CoversClass(CashSessionAlreadyOpenException::class)]
#[CoversClass(CashSessionNotOpenException::class)]
final class CashSessionDomainTest extends TestCase
{
    // ── 1. Open session successfully ──────────────────────────────────────────

    public function testOpenSessionSuccess(): void
    {
        $openedAt = new \DateTimeImmutable('2026-06-27 08:00:00');
        $session = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
            openedAt: $openedAt,
        );

        // Session state
        self::assertSame(CashSessionStatus::OPEN, $session->status());
        self::assertSame('sess-001', $session->id());
        self::assertSame('reg-001', $session->registerId());
        self::assertSame('branch-001', $session->branchId());
        self::assertSame('user-cashier-1', $session->openedByUserId());
        self::assertSame('100.00', $session->openingAmount());
        self::assertSame($openedAt, $session->openedAt());
        self::assertTrue($session->isOpen());

        // Opening-balance movement
        self::assertCount(1, $session->movements());
        $movement = $session->movements()[0];
        self::assertSame('mov-opening-001', $movement->id());
        self::assertSame(MovementType::OPENING_BALANCE, $movement->type());
        self::assertSame('100.00', $movement->amount());
        self::assertSame('Opening balance', $movement->description());
        self::assertSame('user-cashier-1', $movement->registeredByUserId());

        // Domain event
        $events = $session->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CashSessionOpened::class, $events[0]);
        self::assertSame('sess-001', $events[0]->sessionId);
        self::assertSame('reg-001', $events[0]->registerId);
        self::assertSame('user-cashier-1', $events[0]->openedByUserId);
        self::assertSame('100.00', $events[0]->openingAmount);
        self::assertSame($openedAt, $events[0]->occurredAt);

        // Events are consumed (idempotent pull)
        self::assertSame([], $session->pullDomainEvents());
    }

    // ── 2. Prevent double-open ───────────────────────────────────────────────

    public function testPreventDoubleOpen(): void
    {
        $existing = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
        );
        $existing->pullDomainEvents(); // discard

        $this->expectException(CashSessionAlreadyOpenException::class);

        CashSession::open(
            id: 'sess-002',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-2',
            openingAmount: '200.00',
            openingMovementId: 'mov-opening-002',
            currentlyOpenSession: $existing,
        );
    }

    // ── 3. Register movement on OPEN session ─────────────────────────────────

    public function testRegisterMovementOnOpenSession(): void
    {
        $session = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
        );
        $session->pullDomainEvents(); // discard open event

        $registeredAt = new \DateTimeImmutable('2026-06-27 10:00:00');
        $movement = $session->registerMovement(
            movementId: 'mov-income-002',
            type: MovementType::INCOME,
            amount: '250.00',
            description: 'Cash sale payment',
            registeredByUserId: 'user-cashier-1',
            registeredAt: $registeredAt,
        );

        // Movement data
        self::assertSame('mov-income-002', $movement->id());
        self::assertSame('sess-001', $movement->sessionId());
        self::assertSame(MovementType::INCOME, $movement->type());
        self::assertSame('250.00', $movement->amount());
        self::assertSame('Cash sale payment', $movement->description());
        self::assertSame('user-cashier-1', $movement->registeredByUserId());
        self::assertSame($registeredAt, $movement->registeredAt());
        self::assertNull($movement->correctsMovementId());

        // Opening movement + registered movement
        self::assertCount(2, $session->movements());
        self::assertSame($movement, $session->movements()[1]);

        // signedAmount positive for INCOME
        self::assertSame('250.00', $movement->signedAmount());

        // Domain event
        $events = $session->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CashMovementRegistered::class, $events[0]);
        self::assertSame('mov-income-002', $events[0]->movementId);
        self::assertSame('sess-001', $events[0]->sessionId);
        self::assertSame(MovementType::INCOME, $events[0]->movementType);
        self::assertSame('250.00', $events[0]->amount);
        self::assertSame($registeredAt, $events[0]->occurredAt);
    }

    // ── 4. Reject movement on CLOSED session ─────────────────────────────────

    public function testRejectMovementOnClosedSession(): void
    {
        $session = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
        );
        $session->pullDomainEvents();

        $session->close(
            closedByUserId: 'user-manager-1',
            countedAmount: '100.00',
            closedAt: new \DateTimeImmutable('2026-06-27 18:00:00'),
        );
        $session->pullDomainEvents();

        $this->expectException(CashSessionNotOpenException::class);

        $session->registerMovement(
            movementId: 'mov-income-002',
            type: MovementType::INCOME,
            amount: '25.00',
            description: 'Should be rejected',
            registeredByUserId: 'user-cashier-1',
        );
    }

    // ── 5. Close session normally ────────────────────────────────────────────

    public function testCloseSessionNormally(): void
    {
        $session = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
        );
        $session->pullDomainEvents();

        // Add movements: +200 income, -50 expense
        $session->registerMovement(
            movementId: 'mov-income-002',
            type: MovementType::INCOME,
            amount: '200.00',
            description: 'Cash sales',
            registeredByUserId: 'user-cashier-1',
        );
        $session->registerMovement(
            movementId: 'mov-expense-003',
            type: MovementType::EXPENSE,
            amount: '50.00',
            description: 'Petty cash',
            registeredByUserId: 'user-cashier-1',
        );
        $session->registerMovement(
            movementId: 'mov-withdrawal-004',
            type: MovementType::WITHDRAWAL,
            amount: '30.00',
            description: 'Bank deposit',
            registeredByUserId: 'user-cashier-1',
        );
        $session->pullDomainEvents();

        $closedAt = new \DateTimeImmutable('2026-06-27 18:00:00');
        $session->close(
            closedByUserId: 'user-manager-1',
            countedAmount: '218.00',
            closedAt: $closedAt,
        );

        // Status & lifecycle
        self::assertSame(CashSessionStatus::CLOSED, $session->status());
        self::assertFalse($session->isOpen());
        self::assertSame('user-manager-1', $session->closedByUserId());
        self::assertSame($closedAt, $session->closedAt());

        // Expected: opening(100) + income(200) - expense(50) - withdrawal(30) = 220.00
        self::assertSame('220.00', $session->expectedAmount());
        self::assertSame('218.00', $session->countedAmount());
        // Difference: 218 - 220 = -2.00
        self::assertSame('-2.00', $session->differenceAmount());
        self::assertNull($session->reconciliation());

        // Domain event
        $events = $session->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CashSessionClosed::class, $events[0]);
        self::assertSame('sess-001', $events[0]->sessionId);
        self::assertSame('user-manager-1', $events[0]->closedByUserId);
        self::assertSame('218.00', $events[0]->countedAmount);
        self::assertSame('-2.00', $events[0]->differenceAmount);
        self::assertSame($closedAt, $events[0]->occurredAt);
    }

    // ── 6. Close session with reconciliation ─────────────────────────────────

    public function testCloseSessionWithReconciliation(): void
    {
        $session = CashSession::open(
            id: 'sess-recon-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-opening-001',
        );
        $session->pullDomainEvents();

        $closedAt = new \DateTimeImmutable('2026-06-27 18:00:00');
        $reconciliation = $session->close(
            closedByUserId: 'user-manager-1',
            countedAmount: '155.00',
            closedAt: $closedAt,
            reconciliationId: 'rec-001',
            reconciliationNote: 'Counted cash differs from expected — needs supervisor review',
            reconciliationRequestedByUserId: 'user-manager-1',
        );

        // Session status transitions to RECONCILING, not CLOSED
        self::assertSame(CashSessionStatus::RECONCILING, $session->status());
        self::assertFalse($session->isOpen());
        self::assertSame('user-manager-1', $session->closedByUserId());
        self::assertSame($closedAt, $session->closedAt());

        // Close fields are still computed
        self::assertSame('100.00', $session->expectedAmount());
        self::assertSame('155.00', $session->countedAmount());
        self::assertSame('55.00', $session->differenceAmount());

        // CashReconciliation created inline
        self::assertInstanceOf(CashReconciliation::class, $reconciliation);
        self::assertSame($reconciliation, $session->reconciliation());
        self::assertSame('rec-001', $reconciliation->id());
        self::assertSame('sess-recon-001', $reconciliation->sessionId());
        self::assertSame('Counted cash differs from expected — needs supervisor review', $reconciliation->note());
        self::assertSame('user-manager-1', $reconciliation->requestedByUserId());
        self::assertSame($closedAt, $reconciliation->requestedAt());

        // Domain event still emitted (closure recorded even when flagged for reconciliation)
        $events = $session->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CashSessionClosed::class, $events[0]);
    }

    // ── 7. Opening amount normalization ──────────────────────────────────────

    public function testOpeningAmountIsNormalizedToTwoDecimals(): void
    {
        $session = CashSession::open(
            id: 'sess-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '150',
            openingMovementId: 'mov-opening-001',
        );

        self::assertSame('150.00', $session->openingAmount());
        self::assertSame('150.00', $session->movements()[0]->amount());
    }

    // ── 8. Movement signed amounts ──────────────────────────────────────────

    public function testSignedAmountIsPositiveForIncome(): void
    {
        $session = $this->createOpenSession();
        $movement = $session->registerMovement(
            movementId: 'mov-001',
            type: MovementType::INCOME,
            amount: '100.00',
            description: 'Income',
            registeredByUserId: 'user-cashier-1',
        );

        self::assertSame('100.00', $movement->signedAmount());
    }

    public function testSignedAmountIsNegativeForExpense(): void
    {
        $session = $this->createOpenSession();
        $movement = $session->registerMovement(
            movementId: 'mov-002',
            type: MovementType::EXPENSE,
            amount: '75.50',
            description: 'Expense',
            registeredByUserId: 'user-cashier-1',
        );

        self::assertSame('-75.50', $movement->signedAmount());
    }

    public function testSignedAmountIsNegativeForWithdrawal(): void
    {
        $session = $this->createOpenSession();
        $movement = $session->registerMovement(
            movementId: 'mov-003',
            type: MovementType::WITHDRAWAL,
            amount: '200.00',
            description: 'Withdrawal',
            registeredByUserId: 'user-cashier-1',
        );

        self::assertSame('-200.00', $movement->signedAmount());
    }

    public function testSignedAmountIsPositiveForDeposit(): void
    {
        $session = $this->createOpenSession();
        $movement = $session->registerMovement(
            movementId: 'mov-004',
            type: MovementType::DEPOSIT,
            amount: '500.00',
            description: 'Deposit',
            registeredByUserId: 'user-cashier-1',
        );

        self::assertSame('500.00', $movement->signedAmount());
    }

    // ── 9. Opening balance movement type cannot be registered manually ─────────

    public function testCannotRegisterOpeningBalanceManually(): void
    {
        $session = $this->createOpenSession();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Opening balance movement is only created when opening a cash session.');

        $session->registerMovement(
            movementId: 'mov-001',
            type: MovementType::OPENING_BALANCE,
            amount: '50.00',
            description: 'Should not work',
            registeredByUserId: 'user-cashier-1',
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createOpenSession(): CashSession
    {
        $session = CashSession::open(
            id: 'sess-helper-001',
            registerId: 'reg-001',
            branchId: 'branch-001',
            openedByUserId: 'user-cashier-1',
            openingAmount: '100.00',
            openingMovementId: 'mov-helper-opening',
        );
        $session->pullDomainEvents();

        return $session;
    }
}
