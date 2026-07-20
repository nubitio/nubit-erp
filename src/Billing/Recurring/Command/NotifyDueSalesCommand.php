<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Command;

use App\Identity\Notification\NotificationType;
use App\Identity\Notification\UserNotificationService;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Nubit\Platform\Symfony\Console\PerTenantCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scans the sales table for unpaid balances (balance_due > 0, due_date set)
 * and notifies ROLE_ADMIN / ROLE_SUPER_ADMIN users with a daily summary of
 * sales due soon and sales already overdue.
 *
 * Notifications are deduplicated per admin per type within a 20-hour window
 * (same pattern as GenerateStockNotificationsCommand) so the daily cron run
 * does not spam admins with a duplicate summary.
 *
 * Schedule: daily via Symfony Scheduler (see Schedule.php).
 *
 * Usage:
 *   php bin/console app:billing:notify-due-sales              # all tenants
 *   php bin/console app:billing:notify-due-sales --tenant=foo # one tenant
 */
#[AsCommand(
    name: 'app:billing:notify-due-sales',
    description: 'Notify admins about sales due soon or overdue (balance_due > 0)',
)]
final class NotifyDueSalesCommand extends PerTenantCommand
{
    /** Warn this many days before due_date. */
    private const int DUE_SOON_WINDOW_DAYS = 3;

    /** Dedup window in seconds — just under the 24h cron cadence. */
    private const int DEDUP_WINDOW_SECONDS = 72_000;

    public function __construct(
        private readonly Connection $connection,
        private readonly UserNotificationService $notificationService,
    ) {
        parent::__construct();
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = (string) ($this->currentTenantName ?? 'default');

        try {
            $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
            $dueSoonLimit = $today->modify(sprintf('+%d days', self::DUE_SOON_WINDOW_DAYS));

            $dueSoon = $this->fetchDue($today->format('Y-m-d'), $dueSoonLimit->format('Y-m-d'), overdue: false);
            $overdue = $this->fetchDue($today->format('Y-m-d'), $today->format('Y-m-d'), overdue: true);

            if ($dueSoon === [] && $overdue === []) {
                $io->text(sprintf('[%s] No due or overdue sales.', $tenantName));

                return self::SUCCESS;
            }

            if ($dueSoon !== []) {
                $this->notificationService->notifyByRolesDeduped(
                    roles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
                    type: NotificationType::SALE_DUE_SOON,
                    severity: 'info',
                    title: sprintf('%d cuenta(s) por cobrar próximas a vencer', count($dueSoon)),
                    body: $this->summarize($dueSoon),
                    actionUrl: '/sales?filter=balanceDue',
                    withinSeconds: self::DEDUP_WINDOW_SECONDS,
                );
            }

            if ($overdue !== []) {
                $this->notificationService->notifyByRolesDeduped(
                    roles: ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
                    type: NotificationType::SALE_OVERDUE,
                    severity: 'warning',
                    title: sprintf('%d cuenta(s) por cobrar vencida(s)', count($overdue)),
                    body: $this->summarize($overdue),
                    actionUrl: '/sales?filter=balanceDue',
                    withinSeconds: self::DEDUP_WINDOW_SECONDS,
                );
            }

            $io->success(sprintf(
                '[%s] %d due soon, %d overdue — notifications dispatched (deduped).',
                $tenantName,
                count($dueSoon),
                count($overdue),
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('NotifyDueSalesCommand: failed for tenant', [
                'tenant' => $tenantName,
                'exception' => $e->getMessage(),
            ]);
            $io->error(sprintf('[ERROR] Failed for %s: %s', $tenantName, $e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * @return list<array{customer_name: string, balance_due: string, due_date: string}>
     *
     * @throws DbalException
     */
    private function fetchDue(string $from, string $to, bool $overdue): array
    {
        $condition = $overdue ? 's.due_date < :from' : 's.due_date BETWEEN :from AND :to';

        /** @var list<array{customer_name: string, balance_due: string, due_date: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<SQL
                SELECT
                    c.razon_social AS customer_name,
                    s.balance_due  AS balance_due,
                    s.due_date     AS due_date
                FROM sales s
                INNER JOIN cliente c ON c.id = s.customer_id
                WHERE s.balance_due > 0
                  AND s.due_date IS NOT NULL
                  AND s.status != 'VOIDED'
                  AND {$condition}
                ORDER BY s.due_date ASC
                SQL,
            [
                'from' => $from,
                'to' => $to,
            ],
        );

        return $rows;
    }

    /**
     * @param list<array{customer_name: string, balance_due: string, due_date: string}> $rows
     */
    private function summarize(array $rows): string
    {
        $lines = [];
        foreach (array_slice($rows, 0, 10) as $row) {
            $lines[] = sprintf(
                '%s — S/ %s (vence %s)',
                $row['customer_name'],
                $row['balance_due'],
                $row['due_date'],
            );
        }

        if (count($rows) > 10) {
            $lines[] = sprintf('… y %d más.', count($rows) - 10);
        }

        return implode("\n", $lines);
    }
}
