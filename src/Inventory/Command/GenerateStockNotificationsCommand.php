<?php

declare(strict_types=1);

namespace App\Inventory\Command;

use Nubit\Platform\Symfony\Console\PerTenantCommand;
use App\Identity\Notification\NotificationType;
use App\Identity\Notification\UserNotificationService;
use App\Identity\Repository\UserNotificationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Scans all stock_items rows across every tenant and emits a stock_low
 * in-app notification for ROLE_ADMIN / ROLE_SUPER_ADMIN users whenever a
 * product is at or below its configured minimum stock level.
 *
 * Notifications are deduplicated: a new one is only inserted when no unread
 * stock_low notification for the same user was created in the last 24 hours.
 *
 * Schedule: every 6 hours via Symfony Scheduler (see Schedule.php).
 *
 * Usage:
 *   php bin/console app:notification:generate-stock              # all tenants
 *   php bin/console app:notification:generate-stock --tenant=foo # one tenant
 *   php bin/console app:notification:generate-stock --parallel   # parallel
 */
#[AsCommand(
    name: 'app:notification:generate-stock',
    description: 'Emit stock_low in-app notifications for products at or below minimum stock',
)]
final class GenerateStockNotificationsCommand extends PerTenantCommand
{
    /** Deduplication window in seconds (24 hours). */
    private const int DEDUP_WINDOW_SECONDS = 86_400;

    /**
     * rol_id values whose holders should receive stock_low notifications.
     * 1 = ROLE_SUPER_ADMIN, 2 = ROLE_ADMIN — mirrors UserNotificationService::ROLE_INT_MAP.
     */
    private const string ADMIN_ROL_IDS_SQL = 'SELECT usuario_id FROM usuario WHERE rol_id IN (1, 2)';

    public function __construct(
        private readonly UserNotificationRepository $userNotificationRepository,
        private readonly UserNotificationService $notificationService,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    #[Override]
    protected function configure(): void
    {
        parent::configure();
        $this->setHelp(
            <<<'HELP'
            Queries every tenant's stock_items table (V2 source of truth) joined to
            producto.stock_minimo for rows where qty_on_hand <= stock_minimo,
            then emits a <info>stock_low</info> in-app notification to all admin and super-admin
            users — deduplicated: no second notification for the same user within 24 hours.

            This command is wired to the Symfony Scheduler and runs automatically
            every 6 hours.
            HELP
        );
    }

    protected function executeTenantCommand(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tenantName = (string) ($this->currentTenantName ?? 'default');

        try {
            $belowMin = $this->fetchBelowMinStock();

            if ($belowMin === []) {
                $io->text(sprintf('[%s] No products below minimum stock.', $tenantName));

                return self::SUCCESS;
            }

            $since     = new DateTimeImmutable(sprintf('-%d seconds', self::DEDUP_WINDOW_SECONDS));
            $adminRows = $this->fetchAdminUserIds();

            if ($adminRows === []) {
                $io->warning(sprintf('[%s] No admin users found — skipping.', $tenantName));

                return self::SUCCESS;
            }

            $notified = 0;
            $skipped  = 0;

            foreach ($belowMin as $row) {
                $productName   = (string) $row['product_name'];
                $warehouseName = (string) $row['warehouse_name'];
                $qty           = (float)  $row['qty_on_hand'];
                $minQty        = (float)  $row['min_stock'];

                $title = sprintf('Stock bajo: %s', $productName);
                $body  = sprintf(
                    'El producto "%s" (almacén: %s) tiene stock actual de %.3f unidades, '
                    . 'por debajo del mínimo configurado de %.3f unidades.',
                    $productName,
                    $warehouseName,
                    $qty,
                    $minQty,
                );

                foreach ($adminRows as $adminRow) {
                    $userId = (int) $adminRow['usuario_id'];

                    if (
                        $this->userNotificationRepository->existsUnreadForUserAndType(
                            $userId,
                            NotificationType::STOCK_LOW,
                            $since,
                        )
                    ) {
                        ++$skipped;
                        continue;
                    }

                    $this->notificationService->notifyUser(
                        userId: $userId,
                        type: NotificationType::STOCK_LOW,
                        severity: 'warning',
                        title: $title,
                        body: $body,
                        actionUrl: '/product',
                    );

                    ++$notified;
                }
            }

            $io->success(sprintf(
                '[%s] Stock notifications: %d sent, %d deduplicated (24 h window).',
                $tenantName,
                $notified,
                $skipped,
            ));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('GenerateStockNotificationsCommand: failed for tenant', [
                'tenant'    => $tenantName,
                'exception' => $e->getMessage(),
            ]);
            $io->error(sprintf('[ERROR] Failed for %s: %s', $tenantName, $e->getMessage()));

            return self::FAILURE;
        }
    }

    /**
     * Fetch products below minimum stock using V2 stock_items table.
     *
     * @return list<array{product_name: string, warehouse_name: string, qty_on_hand: string, min_stock: string}>
     *
     * @throws DbalException
     */
    private function fetchBelowMinStock(): array
    {
        /** @var list<array{product_name: string, warehouse_name: string, qty_on_hand: string, min_stock: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT
                p.descripcion   AS product_name,
                a.nombre        AS warehouse_name,
                si.qty_on_hand  AS qty_on_hand,
                p.stock_minimo  AS min_stock
            FROM stock_items si
            INNER JOIN producto p ON p.producto_id = si.product_id
            INNER JOIN almacen  a ON a.almacen_id  = si.warehouse_id
            WHERE p.stock_minimo IS NOT NULL
              AND p.stock_minimo > 0
              AND si.qty_on_hand <= p.stock_minimo
            ORDER BY (si.qty_on_hand / p.stock_minimo) ASC
            SQL
        );

        return $rows;
    }

    /**
     * @return list<array{usuario_id: int|string}>
     *
     * @throws DbalException
     */
    private function fetchAdminUserIds(): array
    {
        /** @var list<array{usuario_id: int|string}> $rows */
        $rows = $this->connection->fetchAllAssociative(self::ADMIN_ROL_IDS_SQL);

        return $rows;
    }
}
