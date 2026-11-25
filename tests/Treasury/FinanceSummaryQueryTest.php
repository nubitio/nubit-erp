<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\Direction;
use App\Treasury\Domain\MovementKey;
use App\Treasury\Domain\MovementSource;
use App\Treasury\Domain\TreasuryMovement;
use App\Treasury\Port\MoneyMovementRequest;
use App\Treasury\Report\FinanceAttentionQuery;
use App\Treasury\Report\FinanceSummaryQuery;
use App\Treasury\Report\OpenItemsQuery;
use App\Treasury\Service\PositionQuery;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** The owner's summary: money by kind, what is owed and when, a cautious projection, and what needs attention. */
final class FinanceSummaryQueryTest extends TestCase
{
    private const string TODAY = '2026-11-18';

    public function testMoneyIsSplitByKindAndNeverMixedAcrossCurrencies(): void
    {
        $kit = $this->kit();
        $kit->account('drawer', AccountKind::CashRegister, cashRegisterId: 3);
        $kit->openSession(10, 3, 7, '200.00');
        $this->put($kit, 'bcp', '1000.00');
        $this->put($kit, 'yape', '50.00');
        $this->put($kit, 'susp', '10.00');
        $this->put($kit, 'usd-bank', '300.00');
        $kit->account('gateway', AccountKind::Clearing);
        $kit->movements->append(new TreasuryMovement(
            id: 'gateway-movement',
            accountId: 'gateway',
            signedAmount: '150.00',
            currency: 'PEN',
            occurredAt: new DateTimeImmutable(self::TODAY),
            key: new MovementKey(MovementSource::SalePayment, 'gateway-capture'),
            party: null,
            reference: null,
            description: null,
            transferId: null,
            correctsMovementId: null,
            origin: 'live',
            createdBy: null,
        ));

        $summary = $this->query($kit)->summarize(new DateTimeImmutable(self::TODAY));

        self::assertSame(['PEN', 'USD'], array_column($summary['currencies'], 'currency'));
        $pen = $summary['currencies'][0]['position'];
        self::assertSame(
            [
                'total' => '1260.00',
                'cash' => '200.00',
                'banks' => '1000.00',
                'wallets' => '50.00',
                'clearing' => '150.00',
                'suspense' => '10.00',
            ],
            $pen,
        );
        self::assertSame('1260.00', $summary['currencies'][0]['projectedInThirtyDays']);
        self::assertSame(
            ['PEN' => '1260.00', 'USD' => '300.00'],
            new PositionQuery($kit->accounts, $kit->movements, $kit->drawers)->totalsByCurrency(),
        );
        self::assertSame('300.00', $summary['currencies'][1]['position']['total']);
    }

    public function testReceivablesAndPayablesAreBucketedByDueDateWithTheScreensFilters(): void
    {
        $kit = $this->kit();
        $this->sale($kit, 's1', '100.00', '2026-11-10'); // overdue
        $this->sale($kit, 's2', '50.00', '2026-11-21'); // within 7 days
        $this->sale($kit, 's3', '70.00', '2026-12-08'); // within 30 days
        $this->sale($kit, 's4', '30.00', '2027-02-01'); // later
        $this->sale($kit, 's5', '999.00', '2026-11-10', status: 'VOIDED'); // voided
        $this->sale($kit, 's6', '999.00', '2026-11-10', receivable: 0); // not on account
        $this->sale($kit, 's7', '999.00', '2026-11-10', deleted: true); // deleted
        $this->sale($kit, 's8', '0.00', '2026-11-10'); // nothing left
        $this->bill($kit, 'b1', '40.00', '2026-11-01'); // overdue
        $this->bill($kit, 'b2', '60.00', '2026-11-25'); // within 7 days
        $this->bill($kit, 'b3', '25.00', '2026-12-10'); // within 30 days
        $this->bill($kit, 'b4', '999.00', '2026-11-25', status: 'VOID');

        $row = $this->query($kit)->summarize(new DateTimeImmutable(self::TODAY))['currencies'][0];

        self::assertSame(
            [
                'balance' => '250.00',
                'overdue' => '100.00',
                'dueWithinSevenDays' => '50.00',
                'dueWithinThirtyDays' => '120.00',
                'count' => 4,
                'overdueCount' => 1,
            ],
            $row['receivables'],
        );
        self::assertSame(
            [
                'balance' => '125.00',
                'overdue' => '40.00',
                'dueWithinSevenDays' => '60.00',
                'dueWithinThirtyDays' => '85.00',
                'count' => 3,
                'overdueCount' => 1,
            ],
            $row['payables'],
        );
    }

    public function testTheProjectionCountsComingReceivablesButNotOverdueOnesAndAllPayablesDueOrOverdue(): void
    {
        $kit = $this->kit();
        $this->put($kit, 'bcp', '1000.00');
        $this->sale($kit, 's1', '100.00', '2026-11-10'); // overdue: not counted as coming in
        $this->sale($kit, 's2', '50.00', '2026-11-21'); // coming in
        $this->sale($kit, 's3', '70.00', '2026-12-08'); // coming in
        $this->bill($kit, 'b1', '40.00', '2026-11-01'); // owed now
        $this->bill($kit, 'b2', '60.00', '2026-11-25'); // due
        $this->bill($kit, 'b3', '25.00', '2026-12-10'); // due

        $row = $this->query($kit)->summarize(new DateTimeImmutable(self::TODAY))['currencies'][0];

        // 1000 + (50 + 70) - (60 + 25) - 40
        self::assertSame('995.00', $row['projectedInThirtyDays']);
    }

    public function testACurrencyThatOnlyHasDebtsStillAppearsWithNoMoney(): void
    {
        $kit = TreasuryTestDatabase::create(); // no accounts at all
        $this->sale($kit, 's1', '80.00', '2026-11-20', currency: 'USD');

        $summary = $this->query($kit)->summarize(new DateTimeImmutable(self::TODAY));

        self::assertSame(['USD'], array_column($summary['currencies'], 'currency'));
        self::assertSame('0.00', $summary['currencies'][0]['position']['total']);
        self::assertSame('80.00', $summary['currencies'][0]['receivables']['balance']);
    }

    public function testAttentionListsUnreconciledBankMovementsAndSuspenseMoney(): void
    {
        $kit = $this->kit();
        $this->put($kit, 'bcp', '100.00', 'M-1');
        $this->put($kit, 'bcp', '200.00', 'M-2');
        $this->put($kit, 'yape', '50.00', 'M-3'); // a wallet is not reconciled against a statement
        $this->put($kit, 'susp', '15.00', 'M-4');
        $kit->db->insert('bank_reconciliation_match', [
            'treasury_movement_id' => (string) $kit->db->fetchOne(
                "SELECT id FROM treasury_movement WHERE source_id = 'M-2'",
            ),
        ]);

        $attention = $this->query($kit)->summarize(new DateTimeImmutable(self::TODAY))['attention'];

        self::assertSame(1, $attention['unreconciledBankMovements']);
        self::assertSame([['currency' => 'PEN', 'balance' => '15.00']], $attention['suspense']);
        // The helper records every amount as an opening balance, so only the account that got none is flagged.
        self::assertSame(['Bank usd-bank'], $attention['accountsWithoutOpeningBalance']);
    }

    public function testAnEmptyBusinessHasNothingToShow(): void
    {
        $summary = $this->query(TreasuryTestDatabase::create())->summarize(new DateTimeImmutable(self::TODAY));

        self::assertSame([], $summary['currencies']);
        self::assertSame(0, $summary['attention']['unreconciledBankMovements']);
        self::assertSame([], $summary['attention']['suspense']);
    }

    private function kit(): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        $kit->account('bcp', AccountKind::Bank);
        $kit->account('yape', AccountKind::Wallet);
        $kit->account('susp', AccountKind::Suspense);
        $kit->account('usd-bank', AccountKind::Bank, 'USD');

        return $kit;
    }

    private function query(TreasuryTestDatabase $kit): FinanceSummaryQuery
    {
        $position = new PositionQuery($kit->accounts, $kit->movements, $kit->drawers);

        return new FinanceSummaryQuery(
            $position,
            new OpenItemsQuery($kit->db),
            new FinanceAttentionQuery($kit->db, $position),
        );
    }

    private function put(TreasuryTestDatabase $kit, string $account, string $amount, ?string $key = null): void
    {
        $kit->recorder->record(new MoneyMovementRequest(
            key: new MovementKey(MovementSource::OpeningBalance, $key ?? $account),
            direction: Direction::In,
            amount: $amount,
            currency: $kit->accounts->find($account)->currency ?? 'PEN',
            branchId: TreasuryTestDatabase::BRANCH,
            occurredAt: new DateTimeImmutable('2026-11-01'),
            accountId: $account,
        ));
    }

    private function sale(
        TreasuryTestDatabase $kit,
        string $id,
        string $balance,
        string $due,
        string $status = 'COMPLETED',
        int $receivable = 1,
        bool $deleted = false,
        string $currency = 'PEN',
    ): void {
        $kit->db->insert('sales', [
            'id' => $id,
            'status' => $status,
            'currency' => $currency,
            'due_date' => $due,
            'balance_due' => $balance,
            'receivable_account' => $receivable,
            'deleted_at' => $deleted ? '2026-11-02 10:00:00' : null,
        ]);
    }

    private function bill(
        TreasuryTestDatabase $kit,
        string $id,
        string $balance,
        string $due,
        string $status = 'PENDING',
    ): void {
        $kit->db->insert('supplier_bill', [
            'id' => $id,
            'currency' => 'PEN',
            'due_date' => $due,
            'balance_due' => $balance,
            'status' => $status,
        ]);
    }
}
