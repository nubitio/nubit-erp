<?php

declare(strict_types=1);

namespace App\Accounting\Command;

use App\Accounting\Message\EconomicEvent;
use App\Accounting\Service\DefaultPostingService;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(name: 'app:accounting:backfill', description: 'Post historical sales, purchases and cash movements to the general ledger.')]
final class AccountingBackfillCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DefaultPostingService $postingService,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $posted = 0;

        $sales = $this->connection->fetchAllAssociative(
            "SELECT s.id, s.grand_total, s.tax_total, s.balance_due, s.issued_at, s.currency, s.series, s.number,
                    COALESCE((SELECT SUM(i.qty * COALESCE(i.unit_cost_snapshot, 0)) FROM sale_items i WHERE i.sale_id = s.id), 0) AS cogs
             FROM sales s
             WHERE s.status <> 'VOIDED'",
        );
        foreach ($sales as $sale) {
            $posted += $this->tryPost(new EconomicEvent(
                source: EconomicEvent::SOURCE_SALE,
                sourceId: (string) $sale['id'],
                kind: (float) $sale['balance_due'] > 0 ? EconomicEvent::SALE_CREDIT : EconomicEvent::SALE_CASH,
                occurredOn: (new DateTimeImmutable((string) $sale['issued_at']))->format('Y-m-d'),
                description: 'Venta '.trim((string) $sale['series'].'-'.(string) $sale['number'], '-'),
                total: (string) $sale['grand_total'],
                tax: (string) $sale['tax_total'],
                cogs: (string) $sale['cogs'],
                currency: (string) ($sale['currency'] ?: 'PEN'),
            ), $io);
        }

        $purchases = $this->connection->fetchAllAssociative(
            "SELECT id, grand_total, tax_total, issued_at, currency, receipt_no
             FROM goods_receipts
             WHERE status <> 'VOIDED' AND deleted_at IS NULL",
        );
        foreach ($purchases as $purchase) {
            $posted += $this->tryPost(new EconomicEvent(
                source: EconomicEvent::SOURCE_PURCHASE,
                sourceId: (string) $purchase['id'],
                kind: EconomicEvent::PURCHASE,
                occurredOn: (new DateTimeImmutable((string) $purchase['issued_at']))->format('Y-m-d'),
                description: 'Compra '.(string) ($purchase['receipt_no'] ?? $purchase['id']),
                total: (string) $purchase['grand_total'],
                tax: (string) $purchase['tax_total'],
                currency: (string) ($purchase['currency'] ?: 'PEN'),
            ), $io);
        }

        $io->success(sprintf('Asientos generados o ya existentes: %d hechos económicos procesados.', $posted));

        return Command::SUCCESS;
    }

    private function tryPost(EconomicEvent $event, SymfonyStyle $io): int
    {
        try {
            $this->postingService->post($event);

            return 1;
        } catch (Throwable $exception) {
            $io->warning($event->source.' '.$event->sourceId.': '.$exception->getMessage());

            return 0;
        }
    }
}
