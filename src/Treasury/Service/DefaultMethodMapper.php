<?php

declare(strict_types=1);

namespace App\Treasury\Service;

use App\Treasury\Repository\TreasuryAccountRepository;
use Doctrine\DBAL\Connection;

/**
 * Default mapping of decision 4: Transferencia and Depósito go to the bank account of that currency, but only
 * when there is exactly one (with two, the admin chooses). Wallets and cards need a name from the admin, so
 * they are never defaulted. An existing mapping is never overwritten.
 */
final readonly class DefaultMethodMapper
{
    private const array BANK_METHODS = ['Transferencia', 'Depósito', 'Deposito'];

    public function __construct(
        private Connection $connection,
        private TreasuryAccountRepository $accounts,
    ) {}

    /**
     * @param list<string> $branchIds
     *
     * @return int mappings created
     */
    public function apply(array $branchIds): int
    {
        /** @var list<array{id: string, currency: string}> $banks */
        $banks = $this->connection->fetchAllAssociative('SELECT id, currency FROM bank_account WHERE active = 1');
        $byCurrency = [];
        foreach ($banks as $bank) {
            $byCurrency[$bank['currency']][] = $bank['id'];
        }

        $methods = array_map(
            intval(...),
            $this->connection->fetchFirstColumn(
                'SELECT id FROM payment_method WHERE is_active = 1 AND name IN (?, ?, ?)',
                self::BANK_METHODS,
            ),
        );

        $mapped = 0;
        foreach ($byCurrency as $currency => $bankIds) {
            $account = count($bankIds) === 1 ? $this->accounts->findByBankAccount($bankIds[0]) : null;
            if ($account === null) {
                continue;
            }
            foreach ($branchIds as $branchId) {
                foreach ($methods as $method) {
                    if ($this->accounts->mappedAccountId($branchId, $method, (string) $currency) === null) {
                        $this->accounts->map($branchId, $method, (string) $currency, $account->id);
                        ++$mapped;
                    }
                }
            }
        }

        return $mapped;
    }
}
