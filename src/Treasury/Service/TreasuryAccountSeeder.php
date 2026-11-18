<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Domain\AccountKind;
use App\Treasury\Domain\TreasuryAccount;
use App\Treasury\Repository\TreasuryAccountRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * Brings the accounts in line with what the tenant already has, and never removes anything: one account per
 * active cash register and bank account, one suspense account per branch and currency, and the default
 * mapping of Transferencia and Depósito (see {@see DefaultMethodMapper}).
 * Safe to run any number of times.
 */
final readonly class TreasuryAccountSeeder
{
    public function __construct(
        private Connection $connection,
        private TreasuryAccountRepository $accounts,
        private DefaultMethodMapper $defaults,
    ) {}

    /** @return array{created: list<string>, mapped: int} */
    public function sync(): array
    {
        $created = [];

        /** @var list<array{local_id: string}> $branches */
        $branches = $this->connection->fetchAllAssociative('SELECT local_id FROM local WHERE is_active = 1');

        /** @var list<array{id: int|string, branch_id: string, name: string}> $registers */
        $registers = $this->connection->fetchAllAssociative(
            'SELECT id, branch_id, name FROM cash_register WHERE is_active = 1',
        );
        foreach ($registers as $register) {
            if ($this->accounts->findByCashRegister((int) $register['id']) === null) {
                $name = stripos($register['name'], 'caja') === 0 ? $register['name'] : 'Caja ' . $register['name'];
                $this->add(
                    AccountKind::CashRegister,
                    $name,
                    'PEN',
                    $register['branch_id'],
                    cashRegisterId: (int) $register['id'],
                );
                $created[] = $name;
            }
        }

        /** @var list<array{id: string, alias: string, currency: string}> $banks */
        $banks = $this->connection->fetchAllAssociative(
            'SELECT id, alias, currency FROM bank_account WHERE active = 1 ORDER BY created_at, id',
        );
        $currencies = ['PEN' => true];
        foreach ($banks as $bank) {
            $currencies[$bank['currency']] = true;
            if ($this->accounts->findByBankAccount($bank['id']) === null) {
                $this->add(AccountKind::Bank, $bank['alias'], $bank['currency'], null, bankAccountId: $bank['id']);
                $created[] = $bank['alias'];
            }
        }

        foreach ($branches as $branch) {
            foreach (array_keys($currencies) as $currency) {
                if ($this->accounts->findSuspense($branch['local_id'], $currency) === null) {
                    $this->add(AccountKind::Suspense, 'Sin asignar ' . $currency, $currency, $branch['local_id']);
                    $created[] = 'Sin asignar ' . $currency;
                }
            }
        }

        $branchIds = array_map(static fn(array $branch): string => $branch['local_id'], $branches);

        return ['created' => $created, 'mapped' => $this->defaults->apply($branchIds)];
    }

    private function add(
        AccountKind $kind,
        string $name,
        string $currency,
        ?string $branchId,
        ?int $cashRegisterId = null,
        ?string $bankAccountId = null,
    ): void {
        $this->accounts->add(
            new TreasuryAccount(
                Uuid::v7()->toRfc4122(),
                $kind,
                $name,
                $currency,
                $branchId,
                $cashRegisterId,
                $bankAccountId,
                true,
            ),
        );
    }
}
