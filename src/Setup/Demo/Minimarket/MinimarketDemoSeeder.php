<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\CashRegister\Entity\CashRegister;
use App\Catalog\Entity\UnitOfMeasure;
use App\Identity\Entity\User;
use App\Inventory\Entity\ProductStock;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Purchasing\Service\PurchaseSummaryRebuilder;
use App\Sales\Repository\DashboardRepository;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use SensitiveParameter;

/**
 * The neighbourhood-minimarket demo: forty-five days of sales by two cashiers, one of whom drifts
 * into the pattern the "Control de caja" view is meant to surface. See {@see MinimarketStory}.
 *
 * Meant for a dedicated, freshly reset demo tenant. It never merges into real data.
 */
final readonly class MinimarketDemoSeeder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Connection $connection,
        private DashboardRepository $dashboardRepository,
        private MinimarketMasterData $masterData,
        private MinimarketStaff $staff,
        private MinimarketSalesWriter $salesWriter,
        private MinimarketPurchasesWriter $purchasesWriter,
        private MinimarketExpensesWriter $expensesWriter,
        private MinimarketMenu $menu,
        private PurchaseSummaryRebuilder $purchaseSummaries,
    ) {}

    /**
     * @return array<string, int>
     */
    public function seed(
        User $owner,
        #[SensitiveParameter]
        string $cashierPassword,
        ?DateTimeImmutable $now = null,
    ): array {
        // All or nothing: a reset that fails halfway must not leave a half-built shop for the next visitor.
        $this->connection->beginTransaction();
        try {
            [$branchId, $stats] = $this->build($owner, $cashierPassword, $now);
            $this->connection->commit();
        } catch (\Throwable $exception) {
            $this->connection->rollBack();
            $this->entityManager->clear();

            throw $exception;
        }

        $this->dashboardRepository->invalidate($branchId);

        return $stats;
    }

    /**
     * @return array{0: string, 1: array<string, int>} the branch id and the figures to report
     */
    private function build(User $owner, #[SensitiveParameter] string $cashierPassword, ?DateTimeImmutable $now): array
    {
        $now = ($now ?? new DateTimeImmutable(
            'now',
            new DateTimeZone(MinimarketCatalog::TIMEZONE),
        ))->setTimezone(new DateTimeZone(MinimarketCatalog::TIMEZONE));

        $warehouse = $this->warehouse();
        $branch = $warehouse->getBranch() ?? throw new RuntimeException(
            'El almacén no tiene sucursal. Ejecute el setup inicial.',
        );
        $this->ensureCreditNoteSeries($branch);

        $products = $this->masterData->products($this->unit(), $warehouse);
        $suppliers = $this->masterData->suppliers();
        $context = new SeedContext(
            branch: $branch,
            warehouse: $warehouse,
            cashRegister: $this->cashRegister($branch),
            products: $products,
            customers: $this->masterData->customers(),
            cashiers: $this->staff->cashiers($cashierPassword),
            paymentMethods: $this->masterData->paymentMethods(),
        );

        $written = $this->salesWriter->write(MinimarketStory::build($now), $context);
        $received = $this->purchasesWriter->write(
            $owner,
            $branch,
            $warehouse,
            $suppliers,
            $products,
            $now->setTime(0, 0),
        );

        $this->expensesWriter->write($branch, $suppliers, $now->setTime(0, 0));
        $this->settlePaidBills();
        $this->menu->apply();
        $this->shelfStock($context, $written['sold'], $received);
        $this->summarise($now);

        return [
            (string) $branch->getId(),
            [
                'products' => count($products),
                'customers' => count($context->customers),
                'suppliers' => count($suppliers),
                'cashiers' => count($context->cashiers),
                'shifts' => $written['stats']['shifts'],
                'sales' => $written['stats']['sales'],
                'voided' => $written['stats']['voided'],
                'creditNotes' => $written['stats']['creditNotes'],
                'purchases' => 3,
            ],
        ];
    }

    private function warehouse(): Warehouse
    {
        foreach ($this->entityManager->getRepository(Warehouse::class)->findAll() as $warehouse) {
            $name = $warehouse->getBranch()?->getName() ?? '';
            if (stripos($name, 'E2E') === false && !str_starts_with($name, 'CI ')) {
                return $warehouse;
            }
        }

        throw new RuntimeException('No hay un almacén utilizable. Ejecute el setup inicial.');
    }

    private function unit(): UnitOfMeasure
    {
        $repository = $this->entityManager->getRepository(UnitOfMeasure::class);

        return (
            $repository->findOneBy(['code' => 'NIU']) ?? $repository->findOneBy([]) ?? throw new RuntimeException(
                'No hay unidades de medida. Ejecute el setup inicial.',
            )
        );
    }

    private function cashRegister(Branch $branch): CashRegister
    {
        return (
            $this->entityManager
                ->getRepository(CashRegister::class)
                ->findOneBy(['branch' => $branch]) ?? throw new RuntimeException(
                'La sucursal no tiene caja. Ejecute el setup inicial.',
            )
        );
    }

    /** Credit notes need their own series next to the factura and boleta ones; a fresh tenant may not have them yet. */
    private function ensureCreditNoteSeries(Branch $branch): void
    {
        foreach (['FC01' => 'F001', 'BC01' => 'B001'] as $series => $associated) {
            $exists = $this->connection->fetchOne('SELECT COUNT(*) FROM local_comprobante WHERE local_id = ? AND tipo_comprobante_id = \'07\' AND series = ?', [
                $branch->getId(),
                $series,
            ]);
            if ((int) $exists === 0) {
                $this->connection->executeStatement(
                    'INSERT INTO local_comprobante (local_id, tipo_comprobante_id, series, serie_asociada, is_active) VALUES (?, \'07\', ?, ?, 1)',
                    [$branch->getId(), $series, $associated],
                );
            }
        }
    }

    /**
     * What is on the shelf today: the story's target, adjusted for what was really sold and restocked.
     *
     * @param array<string, int> $sold
     * @param array<string, int> $received
     */
    private function shelfStock(SeedContext $context, array $sold, array $received): void
    {
        $targets = array_column(MinimarketCatalog::products(), 'finalStock', 'code');
        foreach ($context->products as $code => $product) {
            // Start from whatever the shop had, so that selling and restocking lands on the target.
            $opening = max(0, $targets[$code] + ($sold[$code] ?? 0) - ($received[$code] ?? 0));
            $onHand = $opening + ($received[$code] ?? 0) - ($sold[$code] ?? 0);

            $stock = $this->entityManager
                ->getRepository(ProductStock::class)
                ->findOneBy(['product' => $product, 'warehouse' => $context->warehouse]);
            $stock?->setStock((float) $onHand);

            $this->connection->executeStatement(
                'INSERT INTO stock_items (warehouse_id, product_id, qty_on_hand, qty_reserved, updated_at, version)
                 VALUES (:warehouse, :product, :qty, 0, NOW(), 1)
                 ON DUPLICATE KEY UPDATE qty_on_hand = VALUES(qty_on_hand), qty_reserved = 0, updated_at = NOW(), version = version + 1',
                [
                    'warehouse' => (string) $context->warehouse->getId(),
                    'product' => (string) $product->getId(),
                    'qty' => number_format((float) $onHand, 6, '.', ''),
                ],
            );
        }
        $this->entityManager->flush();
    }

    /** The dashboard reads a per-day summary; rebuild it from the sales just written and drop any cached view. */
    /** The bill entity resets its balance to the full amount on insert; a bill that is already paid owes nothing. */
    private function settlePaidBills(): void
    {
        $this->connection->executeStatement('UPDATE supplier_bill SET balance_due = amount - paid_total');
    }

    private function summarise(DateTimeImmutable $now): void
    {
        $this->connection->executeStatement('DELETE FROM branch_daily_summary');
        $this->connection->executeStatement(<<<'SQL'
            INSERT INTO branch_daily_summary
                (summary_date, branch_id, sales_count, gross_sales, discount_total, tax_total, net_sales, refunds_total)
            SELECT
                DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id, COUNT(*),
                COALESCE(SUM(grand_total), 0), COALESCE(SUM(discount_total), 0),
                COALESCE(SUM(tax_total), 0), COALESCE(SUM(grand_total - tax_total), 0), 0
            FROM sales
            WHERE status != 'VOIDED'
            GROUP BY DATE(CONVERT_TZ(issued_at, '+00:00', 'America/Lima')), branch_id
            SQL);

        $this->purchaseSummaries->rebuild(
            $now->modify(sprintf('-%d days', MinimarketStory::HISTORY_DAYS))->format('Y-m-d'),
            $now->format('Y-m-d'),
        );
    }
}
