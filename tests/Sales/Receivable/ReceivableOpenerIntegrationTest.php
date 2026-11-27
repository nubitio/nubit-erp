<?php

declare(strict_types=1);

namespace App\Tests\Sales\Receivable;

use App\Contacts\Entity\Customer;
use App\Sales\Entity\SaleRecord;
use App\Sales\Receivable\ReceivableOpener;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/** Opens receivables against the migrated schema (DAMA rolls every test back). */
final class ReceivableOpenerIntegrationTest extends KernelTestCase
{
    private Connection $db;
    private ReceivableOpener $opener;

    protected function setUp(): void
    {
        self::bootKernel();
        $connection = self::getContainer()->get(Connection::class);
        self::assertInstanceOf(Connection::class, $connection);
        $this->db = $connection;
        $this->opener = new ReceivableOpener($connection);
    }

    public function testACreditSaleOpensOneReceivableWithItsNetPendingAmountAndDueDate(): void
    {
        $sale = $this->sale(Uuid::v4()->toRfc4122(), total: '100.00', balanceDue: '60.00');

        self::assertTrue($this->opener->open($sale));

        $receivable = $this->db->fetchAssociative('SELECT * FROM sales_receivable WHERE sale_id = ?', [$sale->getId()]);
        self::assertIsArray($receivable);
        self::assertSame('100.00', $receivable['total']);
        self::assertSame('60.00', $receivable['net_pending']);
        self::assertSame(9, (int) $receivable['customer_id']);
        $instalments = $this->db->fetchAllAssociative('SELECT * FROM sales_receivable_installment WHERE receivable_id = ?', [
            $receivable['id'],
        ]);
        self::assertCount(1, $instalments);
        self::assertSame('60.00', $instalments[0]['amount']);
        self::assertSame('2026-12-15', $instalments[0]['due_date']);
        self::assertSame('0.00', $instalments[0]['settled_amount']);
    }

    public function testOpeningTheSameSaleTwiceIsANoOp(): void
    {
        $sale = $this->sale(Uuid::v4()->toRfc4122(), total: '100.00', balanceDue: '100.00');

        self::assertTrue($this->opener->open($sale));
        self::assertFalse($this->opener->open($sale));
        self::assertSame(
            1,
            (int) $this->db->fetchOne('SELECT COUNT(*) FROM sales_receivable WHERE sale_id = ?', [$sale->getId()]),
        );
    }

    public function testASaleWithNothingPendingOpensNothing(): void
    {
        $sale = $this->sale(Uuid::v4()->toRfc4122(), total: '100.00', balanceDue: '0.00');

        self::assertFalse($this->opener->open($sale));
    }

    public function testTheDatabaseRefusesInstalmentsThatExceedTheirAmount(): void
    {
        $sale = $this->sale(Uuid::v4()->toRfc4122(), total: '100.00', balanceDue: '100.00');
        $this->opener->open($sale);

        $this->expectException(\Doctrine\DBAL\Exception\DriverException::class);

        $this->db->executeStatement(
            'UPDATE sales_receivable_installment SET settled_amount = 100.01
             WHERE receivable_id = (SELECT id FROM sales_receivable WHERE sale_id = ?)',
            [$sale->getId()],
        );
    }

    private function sale(string $id, string $total, string $balanceDue): SaleRecord
    {
        $customer = $this->createStub(Customer::class);
        $customer->method('getId')->willReturn(9);
        $sale = $this->createPartialMock(SaleRecord::class, ['getId', 'getCustomer']);
        $sale->method('getId')->willReturn($id);
        $sale->method('getCustomer')->willReturn($customer);
        $sale->setGrandTotal($total)->setCurrency('PEN')->setStatus('COMPLETED');
        $sale->setBalanceDue($balanceDue);
        $sale->setIssuedDate(new DateTimeImmutable('2026-11-15'));
        $sale->setDueDate(new DateTimeImmutable('2026-12-15'));

        return $sale;
    }
}
