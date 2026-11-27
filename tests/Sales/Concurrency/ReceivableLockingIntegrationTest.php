<?php

declare(strict_types=1);

namespace App\Tests\Sales\Concurrency;

use App\Sales\Entity\SaleRecord;
use App\Sales\Repository\SaleRecordRepository;
use App\Sales\Service\CustomerAccountLock;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\LockWaitTimeoutException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * G-01 / G-03 against a real MariaDB: a second connection plays "the other cashier". The locks the collection
 * flows rely on must actually block it, and the re-read under lock must see what it committed.
 * Needs the migrated test schema (`make test-back` prepares it).
 */
final class ReceivableLockingIntegrationTest extends KernelTestCase
{
    private const int CUSTOMER = 987654;
    private const string SALE = 'c0c0c0c0-0000-4000-8000-000000000001';

    private EntityManagerInterface $em;
    private Connection $main;
    /** The competing cashier. */
    private Connection $other;
    /** Seeds and cleans committed rows: the main connection lives inside DAMA's per-test transaction. */
    private Connection $admin;

    protected function setUp(): void
    {
        // These tests need real, committing connections; DAMA's per-test transaction would hide every lock.
        StaticDriver::setKeepStaticConnections(false);
        self::ensureKernelShutdown();
        self::bootKernel();
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $em);
        $this->em = $em;
        $this->main = $em->getConnection();
        $this->other = DriverManager::getConnection($this->main->getParams());
        $this->other->executeStatement('SET SESSION innodb_lock_wait_timeout = 1');
        $this->admin = DriverManager::getConnection($this->main->getParams());

        $this->cleanUp();
        $this->admin->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->admin->executeStatement(
            "INSERT INTO sales (id, branch_id, warehouse_id, issued_at, seller_user_id, customer_id, receivable_account, grand_total, balance_due, status)
             VALUES (?, 'b', 'w', NOW(), 1, ?, 1, 100.00, 100.00, 'COMPLETED')",
            [self::SALE, self::CUSTOMER],
        );
        $this->admin->executeStatement(
            "INSERT INTO cliente (id, razon_social, ruc) VALUES (?, 'Concurrency test', '00000000000') ON DUPLICATE KEY UPDATE razon_social = razon_social",
            [self::CUSTOMER],
        );
    }

    protected function tearDown(): void
    {
        if ($this->other->isTransactionActive()) {
            $this->other->rollBack();
        }
        if ($this->main->isTransactionActive()) {
            $this->main->rollBack();
        }
        $this->cleanUp();
        $this->other->close();
        $this->admin->close();
        if ($this->main->isTransactionActive()) {
            $this->main->rollBack();
        }
        $this->main->close();
        StaticDriver::setKeepStaticConnections(true);
        parent::tearDown();
    }

    public function testTheLockedQueryBlocksAnotherWriterOfTheSameSale(): void
    {
        // Control: with nothing locked, the other cashier gets the row at once.
        $this->other->beginTransaction();
        self::assertSame(self::SALE, $this->other->fetchOne('SELECT id FROM sales WHERE id = ? FOR UPDATE', [
            self::SALE,
        ]));
        $this->other->rollBack();

        $this->main->beginTransaction();
        $sales = $this->repository()->lockOpenReceivablesForCustomer(self::CUSTOMER);
        self::assertCount(1, $sales);

        $this->other->beginTransaction();
        $this->expectException(LockWaitTimeoutException::class);
        $this->other->fetchOne('SELECT id FROM sales WHERE id = ? FOR UPDATE', [self::SALE]);
    }

    public function testTheRereadUnderLockSeesWhatAConcurrentCollectionCommitted(): void
    {
        $repository = $this->repository();
        $stale = $repository->findOpenReceivablesForCustomer(self::CUSTOMER);
        self::assertSame('100.00', $stale[0]->getBalanceDue());

        // The other cashier collects 60 and commits while this request still holds the old snapshot.
        $this->admin->executeStatement('UPDATE sales SET balance_due = 40.00 WHERE id = ?', [self::SALE]);

        $this->main->beginTransaction();
        $fresh = $repository->lockOpenReceivablesForCustomer(self::CUSTOMER);

        self::assertSame('40.00', $fresh[0]->getBalanceDue());
    }

    public function testACustomerAccountLockBlocksAnotherWriterOfTheSameCustomer(): void
    {
        $this->other->beginTransaction();
        self::assertNotFalse($this->other->fetchOne('SELECT id FROM cliente WHERE id = ? FOR UPDATE', [
            self::CUSTOMER,
        ]));
        $this->other->rollBack();

        $this->main->beginTransaction();
        new CustomerAccountLock($this->em)->lock(self::CUSTOMER);

        $this->other->beginTransaction();
        $this->expectException(LockWaitTimeoutException::class);
        $this->other->fetchOne('SELECT id FROM cliente WHERE id = ? FOR UPDATE', [self::CUSTOMER]);
    }

    private function repository(): SaleRecordRepository
    {
        $repository = $this->em->getRepository(SaleRecord::class);
        self::assertInstanceOf(SaleRecordRepository::class, $repository);
        $this->em->clear();

        return $repository;
    }

    private function cleanUp(): void
    {
        $this->admin->executeStatement('DELETE FROM sales WHERE id = ?', [self::SALE]);
        $this->admin->executeStatement('DELETE FROM cliente WHERE id = ?', [self::CUSTOMER]);
    }
}
