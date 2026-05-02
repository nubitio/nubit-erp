<?php

declare(strict_types=1);

namespace App\Sales\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
use App\Catalog\Entity\PaymentMethod;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sales:v2:backfill-payments',
    description: 'Create cash sale_payments for legacy Sales V2 rows that have none.',
)]
final class SalesV2BackfillPaymentsCommand extends PerTenantCommand
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Show how many rows would be created without inserting.',
        );
    }

    #[Override]
    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');

        try {
            $this->connection->fetchOne('SELECT 1 FROM sales LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM sale_payments LIMIT 1');
            $this->connection->fetchOne('SELECT 1 FROM payment_method LIMIT 1');
        } catch (TableNotFoundException) {
            $io->warning('Skipping tenant: required Sales V2 tables are not yet migrated.');

            return Command::SUCCESS;
        }

        $cashPaymentMethodId = $this->resolveCashPaymentMethodId();
        if ($cashPaymentMethodId === null) {
            $io->error('Cash payment method not found. Seed payment methods first.');

            return Command::FAILURE;
        }

        if ($dryRun) {
            $missingPayments = (int) $this->connection->fetchOne(
                <<<'SQL'
SELECT COUNT(*)
FROM sales s
WHERE s.legacy_movimiento_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sale_payments sp
      WHERE sp.sale_id = s.id
  )
SQL
            );

            $io->note(sprintf(
                'Dry run: %d sale_payments would be created with payment_method_id=%d (cash).',
                $missingPayments,
                $cashPaymentMethodId,
            ));

            return Command::SUCCESS;
        }

        $this->connection->beginTransaction();

        try {
            $inserted = (int) $this->connection->executeStatement(
                <<<'SQL'
INSERT INTO sale_payments (
    sale_id,
    payment_method_id,
    amount,
    currency,
    tendered_amount,
    change_amount,
    paid_at,
    created_at,
    local_uuid,
    idempotency_key,
    sync_status,
    synced_at,
    device_id,
    version_no,
    last_modified_at
)
SELECT
    s.id AS sale_id,
    :cash_payment_method_id AS payment_method_id,
    COALESCE(s.grand_total, 0.00) AS amount,
    COALESCE(NULLIF(s.currency, ''), 'PEN') AS currency,
    COALESCE(s.grand_total, 0.00) AS tendered_amount,
    0.00 AS change_amount,
    COALESCE(s.issued_at, s.created_at, NOW()) AS paid_at,
    COALESCE(s.created_at, NOW()) AS created_at,
    LOWER(
        CONCAT(
            SUBSTR(MD5(CONCAT('local:sale-payment:legacy:', s.id)), 1, 8), '-',
            SUBSTR(MD5(CONCAT('local:sale-payment:legacy:', s.id)), 9, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale-payment:legacy:', s.id)), 13, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale-payment:legacy:', s.id)), 17, 4), '-',
            SUBSTR(MD5(CONCAT('local:sale-payment:legacy:', s.id)), 21, 12)
        )
    ) AS local_uuid,
    LOWER(
        CONCAT(
            SUBSTR(MD5(CONCAT('idem:sale-payment:legacy:', s.id)), 1, 8), '-',
            SUBSTR(MD5(CONCAT('idem:sale-payment:legacy:', s.id)), 9, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale-payment:legacy:', s.id)), 13, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale-payment:legacy:', s.id)), 17, 4), '-',
            SUBSTR(MD5(CONCAT('idem:sale-payment:legacy:', s.id)), 21, 12)
        )
    ) AS idempotency_key,
    'SYNCED' AS sync_status,
    COALESCE(s.synced_at, s.issued_at, NOW()) AS synced_at,
    NULL AS device_id,
    1 AS version_no,
    NOW() AS last_modified_at
FROM sales s
WHERE s.legacy_movimiento_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1
      FROM sale_payments sp
      WHERE sp.sale_id = s.id
  )
SQL,
                ['cash_payment_method_id' => $cashPaymentMethodId]
            );

            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $this->logger->error('Failed to backfill sale payments', [
                'tenant' => $this->currentTenantName,
                'cash_payment_method_id' => $cashPaymentMethodId,
                'exception' => $exception,
            ]);
            $io->error('Failed to create sale payments: ' . $exception->getMessage());

            return Command::FAILURE;
        }

        if ($inserted === 0) {
            $io->success('No legacy sales without payment were found.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            'Created %d sale_payments for legacy sales using payment_method_id=%d (cash).',
            $inserted,
            $cashPaymentMethodId,
        ));

        return Command::SUCCESS;
    }

    private function resolveCashPaymentMethodId(): ?int
    {
        $id = $this->connection->fetchOne(
            'SELECT id FROM payment_method WHERE id = :id LIMIT 1',
            ['id' => PaymentMethod::CASH],
        );

        if ($id !== false) {
            return (int) $id;
        }

        $fallback = $this->connection->fetchOne(
            "SELECT id FROM payment_method WHERE LOWER(name) IN ('efectivo', 'cash') ORDER BY id ASC LIMIT 1"
        );

        return $fallback === false ? null : (int) $fallback;
    }
}
