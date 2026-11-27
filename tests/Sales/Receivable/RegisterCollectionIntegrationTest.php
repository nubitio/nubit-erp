<?php

declare(strict_types=1);

namespace App\Tests\Sales\Receivable;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Enum\SettlementBehavior;
use App\Identity\Entity\User;
use App\Integration\Entity\ApiClient;
use App\Sales\Port\ConfirmIntegrationCredit;
use App\Sales\Receivable\CollectionRequest;
use App\Sales\Receivable\RegisterCollection;
use App\Sales\Receivable\TenderInput;
use App\Settings\Service\TenantSettingsService;
use App\Tests\ReleaseCritical\Support\CoreSaleFixture;
use App\Tests\ReleaseCritical\Support\CoreSaleWorld;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * T1.4 end to end against the migrated schema: one collection of 250 over invoices of 80, 120 and 240, received
 * as 100 + 150 through two methods. DAMA rolls every test back.
 */
final class RegisterCollectionIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $db;
    private RegisterCollection $register;
    private CoreSaleWorld $world;
    private PaymentMethod $methodA;
    private PaymentMethod $methodB;
    /** @var list<string> */
    private array $saleIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $settings = $container->get(TenantSettingsService::class);
        $register = $container->get(RegisterCollection::class);
        $confirm = $container->get(ConfirmIntegrationCredit::class);
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        self::assertInstanceOf(TenantSettingsService::class, $settings);
        self::assertInstanceOf(RegisterCollection::class, $register);
        self::assertInstanceOf(ConfirmIntegrationCredit::class, $confirm);
        if (!$em->getConnection()->createSchemaManager()->tablesExist([
            'sales_collection',
            'integration_sales_request',
        ])) {
            self::markTestSkipped('Run against a migrated test tenant.');
        }
        $this->em = $em;
        $this->db = $em->getConnection();
        $this->register = $register;

        $fixture = new CoreSaleFixture($em, $settings);
        $this->world = $fixture->seed('100.000000');
        $fixture->closeSession($this->world->cashSession);
        $client = new ApiClient('Collections test', ['sales:write']);
        $this->methodA = $this->method('A');
        $this->methodB = $this->method('B');
        $em->persist($client);
        $em->flush();

        // The product sells at 10.00 (a different price needs a permission), so the totals come from the quantity.
        foreach (['8' => '80.00', '12' => '120.00', '24' => '240.00'] as $qty => $total) {
            $this->saleIds[] = $this->db->transactional(fn(): string => $confirm->confirmIntegrationCredit(
                (string) $client->getId(),
                (string) $this->world->branch->getId(),
                (string) $this->world->warehouse->getId(),
                (int) $this->world->customer->getId(),
                [[
                    'product' => (string) $this->world->product->getId(),
                    'qty' => (string) $qty,
                    'unitPrice' => '10.00',
                ]],
                $total,
            ));
        }
    }

    public function testOneCollectionOverTwoTendersSettlesTheInvoicesByDueDate(): void
    {
        $result = $this->register->register($this->request('key-1', '100', '150'));

        self::assertSame('250.00', $result->total);
        self::assertSame('0.00', $result->unapplied);
        self::assertSame(['80.00', '120.00', '50.00'], array_column($result->allocations, 'amount'));
        self::assertCount(2, $result->tenders);

        // New tables.
        self::assertSame(1, $this->rows('sales_collection'));
        self::assertSame(2, $this->rows('sales_collection_tender'));
        self::assertSame(3, $this->rows('sales_collection_allocation'));
        self::assertSame(
            ['80.00', '120.00', '50.00'],
            $this->db->fetchFirstColumn(
                'SELECT i.settled_amount FROM sales_receivable_installment i JOIN sales_receivable r ON r.id = i.receivable_id WHERE r.customer_id = ? ORDER BY r.created_at, i.id',
                [(int) $this->world->customer->getId()],
            ),
        );

        // Legacy structures stay consistent: A and B settled, C partial, a payment row per slice.
        self::assertSame(
            ['0.00', '0.00', '190.00'],
            array_map(
                fn(string $id) => (string) $this->db->fetchOne('SELECT balance_due FROM sales WHERE id = ?', [$id]),
                $this->saleIds,
            ),
        );
        self::assertSame(
            4,
            (int) $this->db->fetchOne('SELECT COUNT(*) FROM sale_payments WHERE sale_id IN (?, ?, ?)', $this->saleIds),
        );
        self::assertSame(
            '250.00',
            (string) $this->db->fetchOne("SELECT credit FROM customer_account_entries WHERE source = 'collection' AND customer_id = ?", [(int) $this->world->customer->getId()]),
        );
    }

    public function testARetryWithTheSameKeyReturnsTheFirstCollectionAndBooksNothingAgain(): void
    {
        $first = $this->register->register($this->request('key-2', '100'));
        $again = $this->register->register($this->request('key-2', '100'));

        self::assertFalse($first->replayed);
        self::assertTrue($again->replayed);
        self::assertSame($first->collectionId, $again->collectionId);
        self::assertSame(1, $this->rows('sales_collection'));
        self::assertSame(2, $this->rows('sales_collection_allocation'));
        self::assertSame(
            '340.00',
            (string) $this->db->fetchOne('SELECT SUM(balance_due) FROM sales WHERE id IN (?, ?, ?)', $this->saleIds),
        );
    }

    public function testAnAmountAboveTheDebtLeavesTheRestAsTheCustomersAdvance(): void
    {
        $result = $this->register->register($this->request('key-3', '500'));

        self::assertSame(['80.00', '120.00', '240.00'], array_column($result->allocations, 'amount'));
        self::assertSame('60.00', $result->unapplied);
        self::assertSame(
            '60.00',
            (string) $this->db->fetchOne("SELECT credit FROM customer_account_entries WHERE source = 'collection_excess' AND customer_id = ?", [(int) $this->world->customer->getId()]),
        );
        self::assertSame(
            '0.00',
            (string) $this->db->fetchOne('SELECT SUM(balance_due) FROM sales WHERE id IN (?, ?, ?)', $this->saleIds),
        );
    }

    public function testAManualSplitIsHonoured(): void
    {
        // Receivables are opened by the first collection; open them with a tiny one and then split by hand.
        $this->register->register($this->request('key-4a', '10'));
        $items = $this->db->fetchAllAssociative('SELECT i.id FROM sales_receivable_installment i JOIN sales_receivable r ON r.id = i.receivable_id WHERE r.sale_id = ?', [
            $this->saleIds[2],
        ]);

        $result = $this->register->register(new CollectionRequest(
            customerId: (int) $this->world->customer->getId(),
            currency: 'PEN',
            receivedOn: new DateTimeImmutable('2026-11-27'),
            tenders: [new TenderInput($this->methodA, '100')],
            allocations: [['installmentId' => (string) $items[0]['id'], 'amount' => '100']],
            idempotencyKey: 'key-4b',
            createdBy: new User(),
        ));

        self::assertSame([(string) $items[0]['id']], array_column($result->allocations, 'installmentId'));
        self::assertSame(
            '140.00',
            (string) $this->db->fetchOne('SELECT balance_due FROM sales WHERE id = ?', [$this->saleIds[2]]),
        );
    }

    /** One tender per amount, alternating the two methods. */
    private function request(string $key, string $first, string ...$rest): CollectionRequest
    {
        $tenders = [new TenderInput($this->methodA, $first)];
        foreach (array_values($rest) as $i => $amount) {
            $tenders[] = new TenderInput(($i % 2) === 0 ? $this->methodB : $this->methodA, $amount);
        }

        return new CollectionRequest(
            customerId: (int) $this->world->customer->getId(),
            currency: 'PEN',
            receivedOn: new DateTimeImmutable('2026-11-27'),
            tenders: $tenders,
            allocations: [],
            idempotencyKey: $key,
            createdBy: new User(),
        );
    }

    private function method(string $name): PaymentMethod
    {
        $method = new PaymentMethod()
            ->setName('Collect ' . $name . bin2hex(random_bytes(3)))
            ->setSettlementBehavior(SettlementBehavior::COLLECTED)
            ->setIsActive(true);
        $this->em->persist($method);

        return $method;
    }

    private function rows(string $table): int
    {
        return (int) $this->db->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }
}
