<?php

declare(strict_types=1);

namespace App\Tests\Treasury;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Service\DefaultMethodMapper;
use App\Treasury\Service\TreasuryAccountSeeder;
use PHPUnit\Framework\TestCase;

final class TreasuryAccountSeederTest extends TestCase
{
    private const string MAIN = 'branch-main';
    private const string SECOND = 'branch-second';

    public function testItCreatesOneAccountPerRegisterAndBankAndSuspensePerBranchAndCurrency(): void
    {
        $kit = $this->tenant(banks: [
            ['b1', 'BCP Soles',    'PEN'],
            ['b2', 'BBVA Dólares', 'USD'],
        ]);

        $result = $this->seeder($kit)->sync();

        $kinds = ['cash_register' => [], 'bank' => [], 'suspense' => []];
        foreach ($kit->accounts->all() as $account) {
            $kinds[$account->kind->value][] = $account->name . ' ' . $account->currency;
        }
        self::assertCount(1, $kinds['cash_register']);
        self::assertSame(['BBVA Dólares USD', 'BCP Soles PEN'], $this->sorted($kinds['bank']));
        self::assertCount(4, $kinds['suspense'], '2 branches x (PEN, USD)');
        self::assertCount(7, $result['created']);
        self::assertNotNull($kit->accounts->findSuspense(self::SECOND, 'USD'));
    }

    public function testRunningItAgainChangesNothing(): void
    {
        $kit = $this->tenant(banks: [['b1', 'BCP Soles', 'PEN']]);
        $this->seeder($kit)->sync();
        $before = (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_account');

        $second = $this->seeder($kit)->sync();

        self::assertSame([], $second['created']);
        self::assertSame(0, $second['mapped']);
        self::assertSame($before, (int) $kit->db->fetchOne('SELECT COUNT(*) FROM treasury_account'));
    }

    public function testARegisterNamedCajaIsNotPrefixedTwice(): void
    {
        $kit = $this->tenant(registerName: 'Caja Principal');
        $this->seeder($kit)->sync();

        $names = array_map(
            static fn($a): string => $a->name,
            array_filter($kit->accounts->all(), static fn($a): bool => $a->kind === AccountKind::CashRegister),
        );
        self::assertSame(['Caja Principal'], array_values($names));
    }

    public function testInactiveRegistersAndBanksAreSkipped(): void
    {
        $kit = $this->tenant(banks: [['b1', 'BCP Soles', 'PEN']]);
        $kit->db->executeStatement('UPDATE bank_account SET active = 0');
        $kit->db->executeStatement('UPDATE cash_register SET is_active = 0');

        $this->seeder($kit)->sync();

        self::assertSame(
            [AccountKind::Suspense],
            array_unique(array_map(static fn($a): AccountKind => $a->kind, $kit->accounts->all()), SORT_REGULAR),
        );
    }

    public function testTransferenciaAndDepositoDefaultToTheOnlyBankOfThatCurrency(): void
    {
        $kit = $this->tenant(banks: [['b1', 'BCP Soles', 'PEN']]);

        $result = $this->seeder($kit)->sync();

        $bank = $kit->accounts->findByBankAccount('b1');
        self::assertNotNull($bank);
        self::assertSame(4, $result['mapped'], '2 methods x 2 branches');
        self::assertSame($bank->id, $kit->accounts->mappedAccountId(self::MAIN, 5, 'PEN'));
        self::assertSame($bank->id, $kit->accounts->mappedAccountId(self::SECOND, 9, 'PEN'));
        self::assertNull($kit->accounts->mappedAccountId(self::MAIN, 6, 'PEN'), 'Yape needs a wallet the admin names');
        self::assertNull($kit->accounts->mappedAccountId(self::MAIN, 5, 'USD'));
    }

    public function testWithTwoBanksInACurrencyNothingIsGuessed(): void
    {
        $kit = $this->tenant(banks: [['b1', 'BCP', 'PEN'], ['b2', 'BBVA', 'PEN']]);

        $result = $this->seeder($kit)->sync();

        self::assertSame(0, $result['mapped']);
        self::assertNull($kit->accounts->mappedAccountId(self::MAIN, 5, 'PEN'));
    }

    public function testAnExistingMappingIsNeverOverwritten(): void
    {
        $kit = $this->tenant(banks: [['b1', 'BCP', 'PEN']]);
        $kit->account('mine', AccountKind::Wallet);
        $kit->accounts->map(self::MAIN, 5, 'PEN', 'mine');

        $this->seeder($kit)->sync();

        self::assertSame('mine', $kit->accounts->mappedAccountId(self::MAIN, 5, 'PEN'));
    }

    /** @param list<array{0: string, 1: string, 2: string}> $banks */
    private function tenant(array $banks = [], string $registerName = 'Principal'): TreasuryTestDatabase
    {
        $kit = TreasuryTestDatabase::create();
        foreach ([self::MAIN, self::SECOND] as $branch) {
            $kit->db->insert('local', ['local_id' => $branch, 'descripcion' => $branch]);
        }
        $kit->db->insert('cash_register', ['id' => 1, 'branch_id' => self::MAIN, 'name' => $registerName]);
        foreach ([[5, 'Transferencia'], [6, 'Yape'], [9, 'Depósito']] as [$id, $name]) {
            $kit->db->insert('payment_method', ['id' => $id, 'name' => $name]);
        }
        foreach ($banks as [$id, $alias, $currency]) {
            $kit->db->insert('bank_account', ['id' => $id, 'alias' => $alias, 'currency' => $currency]);
        }

        return $kit;
    }

    private function seeder(TreasuryTestDatabase $kit): TreasuryAccountSeeder
    {
        return new TreasuryAccountSeeder($kit->db, $kit->accounts, new DefaultMethodMapper($kit->db, $kit->accounts));
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
