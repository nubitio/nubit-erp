<?php

declare(strict_types=1);

namespace App\Sales\Receivable;

use App\Sales\Repository\SaleRecordRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Opens the receivable of every credit sale that still owes money and has none (finance refactoring, T1.6).
 *
 * Only what the data proves is created: one instalment for the balance owed now, due on the sale's own due date,
 * marked `backfill`. Past collections are NOT reconstructed into allocations: the legacy payments, applications and
 * ledger already hold that history, and inventing a distribution of it would be guessing. Idempotent: a sale that
 * has a receivable is never a candidate, so a re-run or an interrupted run simply continues.
 */
readonly class ReceivablesBackfill
{
    public function __construct(
        private Connection $connection,
        private SaleRecordRepository $sales,
        private ReceivableOpener $opener,
        private EntityManagerInterface $entityManager,
    ) {}

    /** @return array{candidates: int, opened: int, skipped: int} */
    public function run(bool $dryRun, int $batchSize = 200): array
    {
        $candidates = 0;
        $opened = 0;
        $after = '';
        do {
            $ids = $this->nextBatch($after, $batchSize);
            foreach ($ids as $id) {
                ++$candidates;
                $sale = $dryRun ? null : $this->sales->find($id);
                $opened += $sale !== null && $this->opener->open($sale, 'backfill') ? 1 : 0;
            }
            $after = $ids === [] ? $after : $ids[array_key_last($ids)];
            $this->entityManager->clear();
        } while (count($ids) === $batchSize);

        return ['candidates' => $candidates, 'opened' => $opened, 'skipped' => $dryRun ? 0 : $candidates - $opened];
    }

    /** @return list<string> */
    private function nextBatch(string $afterId, int $limit): array
    {
        /** @var list<string> */
        return $this->connection->fetchFirstColumn("SELECT s.id FROM sales s
             WHERE s.id > ? AND s.receivable_account = 1 AND s.balance_due > 0 AND s.status <> 'VOIDED'
               AND s.deleted_at IS NULL AND s.customer_id IS NOT NULL
               AND NOT EXISTS (SELECT 1 FROM sales_receivable r WHERE r.sale_id = s.id)
             ORDER BY s.id LIMIT " . max(1, $limit), [$afterId]);
    }
}
