<?php

declare(strict_types=1);

namespace App\Tests\Onboarding;

use App\Catalog\Entity\PaymentMethod;
use App\Catalog\Repository\PaymentMethodRepository;
use App\Contacts\Entity\Customer;
use App\Contacts\Repository\CustomerRepository;
use App\Identity\Auth\BranchContextServiceInterface;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Inventory\Repository\WarehouseRepository;
use App\Onboarding\OpeningReceivableImporter;
use App\Onboarding\OpeningReceivableRowImporter;
use App\Organization\Entity\Branch;
use App\Organization\Repository\BranchRepository;
use App\Sales\Entity\CustomerAccountEntry;
use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CustomerAccountLedger;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;

#[AllowMockObjectsWithoutExpectations]
final class OpeningReceivableImporterTest extends TestCase
{
    public function testImportUsesRowIssuedDateForLedgerAndSaleFields(): void
    {
        $ledger = new RecordedCharges();
        $importer = $this->makeImporter(ledgerSink: $ledger);

        $result = $importer->import([[
            'ruc' => '20100100101',
            'nombre' => 'Cliente Viejo SAC',
            'monto' => '500.00',
            'vencimiento' => '2026-09-30',
            'fecha de emision' => '2026-03-10',
            'numero' => 'F001-1',
        ]]);

        self::assertSame(1, $result['created']);
        self::assertCount(1, $ledger->calls);
        self::assertSame('2026-03-10', $ledger->calls[0]['occurredOn']->format('Y-m-d'));
    }

    public function testImportDefaultsToTodayWhenIssuedDateMissing(): void
    {
        $ledger = new RecordedCharges();
        $importer = $this->makeImporter(ledgerSink: $ledger);

        $importer->import([[
            'ruc' => '20100100102',
            'nombre' => 'Cliente Nuevo SAC',
            'monto' => '500.00',
            'vencimiento' => '2026-09-30',
            'numero' => 'F001-2',
        ]]);

        self::assertCount(1, $ledger->calls);
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Lima'));
        self::assertSame($today->format('Y-m-d'), $ledger->calls[0]['occurredOn']->format('Y-m-d'));
    }

    public function testReimportingSameRowIsSkippedNotDuplicated(): void
    {
        $ledger = new RecordedCharges();
        $existingRetryKeys = [];
        $importer = $this->makeImporter(existingRetryKeys: $existingRetryKeys, ledgerSink: $ledger);

        $row = [
            'ruc' => '20100100103',
            'nombre' => 'Cliente Duplicado SAC',
            'monto' => '300.00',
            'vencimiento' => '2026-09-30',
            'numero' => 'F001-3',
        ];

        $first = $importer->import([$row]);
        self::assertSame(1, $first['created']);
        self::assertSame(0, $first['skipped']);

        // Simulate the row now existing (as the first import would have persisted it).
        $existingRetryKeys[] = $this->retryKeyFor('20100100103', 'F001-3', '300.00', '2026-09-30');

        $second = $importer->import([$row]);
        self::assertSame(0, $second['created']);
        self::assertSame(1, $second['skipped']);

        // Only the first import should have booked a ledger charge.
        self::assertCount(1, $ledger->calls);
    }

    public function testImportRecordsChargeOnCustomerAccountLedgerForEachRowIncludingNewCustomers(): void
    {
        $ledger = new RecordedCharges();
        $importer = $this->makeImporter(ledgerSink: $ledger);

        $result = $importer->import([
            [
                'ruc' => '20100100104',
                'nombre' => 'Cliente A SAC',
                'monto' => '100.00',
                'vencimiento' => '2026-09-30',
                'numero' => 'F001-4',
            ],
            [
                'ruc' => '20100100105',
                'nombre' => 'Cliente B SAC',
                'monto' => '200.00',
                'vencimiento' => '2026-09-30',
                'numero' => 'F001-5',
            ],
        ]);

        self::assertSame(2, $result['created']);
        self::assertCount(2, $ledger->calls);
        foreach ($ledger->calls as $call) {
            self::assertGreaterThan(0, $call['customerId']);
        }
    }

    /**
     * @param list<string> $existingRetryKeys reference the test can append to,
     *        simulating rows that were already imported in a previous run
     */
    private function makeImporter(
        array &$existingRetryKeys = [],
        ?RecordedCharges $ledgerSink = null,
    ): OpeningReceivableImporter {
        $ledgerSink ??= new RecordedCharges();
        $pendingCustomers = new PendingCustomers();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(static fn(callable $fn) => $fn());
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use ($pendingCustomers): void {
                if ($entity instanceof SaleRecord) {
                    // SaleRecord uses a CUSTOM id generator assigned synchronously on persist().
                    new \ReflectionClass($entity)
                        ->getProperty('id')
                        ->setValue($entity, 'sale-' . bin2hex(random_bytes(4)));
                }
                if ($entity instanceof Customer) {
                    $pendingCustomers->items[] = $entity;
                }
            });
        $entityManager
            ->method('flush')
            ->willReturnCallback(static function () use ($pendingCustomers): void {
                foreach ($pendingCustomers->items as $customer) {
                    if ($customer->getId() === null) {
                        new \ReflectionClass($customer)
                            ->getProperty('id')
                            ->setValue($customer, $pendingCustomers->nextId++);
                    }
                }
                $pendingCustomers->items = [];
            });
        $entityManager->method('clear');

        $customerRepository = $this->createStub(CustomerRepository::class);
        $customerRepository->method('findOneBy')->willReturn(null);

        $branchRepository = $this->createStub(BranchRepository::class);
        $branchRepository->method('findOneBy')->willReturn($this->createStub(Branch::class));

        $warehouseRepository = $this->createStub(WarehouseRepository::class);
        $warehouseRepository->method('findOneBy')->willReturn($this->createStub(Warehouse::class));

        $paymentMethodRepository = $this->createStub(PaymentMethodRepository::class);
        $paymentMethodRepository->method('findOneBy')->willReturn($this->createStub(PaymentMethod::class));

        $branchContext = $this->createStub(BranchContextServiceInterface::class);
        $branchContext->method('getCurrentBranchId')->willReturn(null);

        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        $saleRecordRepository = $this->createStub(SaleRecordRepository::class);
        $saleRecordRepository
            ->method('hasOfflineRetryKey')
            ->willReturnCallback(static function (string $key) use (&$existingRetryKeys): bool {
                return in_array($key, $existingRetryKeys, true);
            });

        $customerAccountLedger = $this->createMock(CustomerAccountLedger::class);
        $customerAccountLedger
            ->method('recordCharge')
            ->willReturnCallback(function (
                int $customerId,
                string $saleId,
                DateTimeImmutable $occurredOn,
                string $amount,
            ) use ($ledgerSink) {
                $ledgerSink->calls[] = [
                    'customerId' => $customerId,
                    'saleId' => $saleId,
                    'occurredOn' => $occurredOn,
                    'amount' => $amount,
                ];

                return $this->createStub(CustomerAccountEntry::class);
            });

        $rowImporter = new OpeningReceivableRowImporter($entityManager, $customerRepository, $saleRecordRepository);

        return new OpeningReceivableImporter(
            $entityManager,
            $branchRepository,
            $warehouseRepository,
            $paymentMethodRepository,
            $branchContext,
            $security,
            $customerAccountLedger,
            $rowImporter,
        );
    }

    private function retryKeyFor(string $ruc, string $number, string $amount, string $dueDate): string
    {
        $seed = sprintf('opening-balance:%s:%s:%s:%s', $ruc, $number, $amount, $dueDate);
        $hex = md5($seed);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }
}

/**
 * Captures the arguments {@see CustomerAccountLedger::recordCharge()} was
 * called with, so a unit test can assert on them without a database.
 */
final class RecordedCharges
{
    /** @var list<array{customerId: int, saleId: string, occurredOn: DateTimeImmutable, amount: string}> */
    public array $calls = [];
}

/**
 * Simulates Doctrine assigning auto-increment ids to newly-persisted
 * customers only once the unit of work is flushed, not at persist() time.
 */
final class PendingCustomers
{
    /** @var list<Customer> */
    public array $items = [];
    public int $nextId = 100;
}
