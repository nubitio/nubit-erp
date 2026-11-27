<?php

declare(strict_types=1);

namespace App\Tests\Sales\Receivable;

use App\Integration\Entity\ApiClient;
use App\Sales\Port\ConfirmIntegrationCredit;
use App\Sales\Receivable\ReceivablesBackfill;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** A credit sale that predates the module gets its receivable once, and only for the balance still owed. */
final class ReceivablesBackfillIntegrationTest extends KernelTestCase
{
    private Connection $db;
    private ReceivablesBackfill $backfill;
    private string $saleId;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $settings = $container->get(TenantSettingsService::class);
        $backfill = $container->get(ReceivablesBackfill::class);
        $confirm = $container->get(ConfirmIntegrationCredit::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(TenantSettingsService::class, $settings);
        self::assertInstanceOf(ReceivablesBackfill::class, $backfill);
        self::assertInstanceOf(ConfirmIntegrationCredit::class, $confirm);
        if (!$em->getConnection()->createSchemaManager()->tablesExist([
            'sales_receivable',
            'integration_sales_request',
        ])) {
            self::markTestSkipped('Run against a migrated test tenant.');
        }
        $this->db = $em->getConnection();
        $this->backfill = $backfill;

        $world = new CoreSaleFixture($em, $settings)->seed('100.000000');
        $client = new ApiClient('Backfill test', ['sales:write']);
        $em->persist($client);
        $em->flush();
        $this->saleId = $this->db->transactional(fn(): string => $confirm->confirmIntegrationCredit(
            (string) $client->getId(),
            (string) $world->branch->getId(),
            (string) $world->warehouse->getId(),
            (int) $world->customer->getId(),
            [['product' => (string) $world->product->getId(), 'qty' => '5', 'unitPrice' => '10.00']],
            '50.00',
        ));
        // Simulate a sale from before the module: no receivable row.
        $this->db->executeStatement('DELETE i FROM sales_receivable_installment i JOIN sales_receivable r ON r.id = i.receivable_id WHERE r.sale_id = ?', [
            $this->saleId,
        ]);
        $this->db->executeStatement('DELETE FROM sales_receivable WHERE sale_id = ?', [$this->saleId]);
    }

    public function testADryRunCountsTheSaleAndChangesNothing(): void
    {
        $result = $this->backfill->run(dryRun: true);

        self::assertGreaterThanOrEqual(1, $result['candidates']);
        self::assertSame(0, $result['opened']);
        self::assertSame(0, $this->receivables());
    }

    public function testTheBackfillOpensTheReceivableOnceForTheBalanceStillOwed(): void
    {
        $first = $this->backfill->run(dryRun: false);
        $second = $this->backfill->run(dryRun: false);

        self::assertGreaterThanOrEqual(1, $first['opened']);
        self::assertSame(1, $this->receivables());
        $row = $this->db->fetchAssociative(
            'SELECT r.origin, r.net_pending, i.amount, i.settled_amount FROM sales_receivable r JOIN sales_receivable_installment i ON i.receivable_id = r.id WHERE r.sale_id = ?',
            [$this->saleId],
        );
        self::assertIsArray($row);
        self::assertSame('backfill', $row['origin']);
        self::assertSame('50.00', $row['net_pending']);
        self::assertSame('50.00', $row['amount']);
        self::assertSame('0.00', $row['settled_amount']);
        // Nothing left to open the second time.
        self::assertSame(0, $second['opened']);
    }

    private function receivables(): int
    {
        return (int) $this->db->fetchOne('SELECT COUNT(*) FROM sales_receivable WHERE sale_id = ?', [$this->saleId]);
    }
}
