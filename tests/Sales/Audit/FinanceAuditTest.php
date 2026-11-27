<?php

declare(strict_types=1);

namespace App\Tests\Sales\Audit;

use App\AccountsPayable\Audit\PayablesAudit;
use App\Sales\Audit\ReceivablesAudit;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;

/** The audit rules flag real inconsistencies and stay silent on a coherent database. */
final class FinanceAuditTest extends TestCase
{
    private Connection $db;

    protected function setUp(): void
    {
        $this->db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ([
            'CREATE TABLE sales (id TEXT, status TEXT, deleted_at TEXT, grand_total NUMERIC, paid_total NUMERIC, balance_due NUMERIC, currency TEXT)',
            'CREATE TABLE sale_payments (id INTEGER, sale_id TEXT, amount NUMERIC)',
            'CREATE TABLE customer_account_entries (id INTEGER PRIMARY KEY, customer_id INT, entry_type TEXT, debit NUMERIC, credit NUMERIC, running_balance NUMERIC, source TEXT, source_id TEXT, sale_id TEXT)',
            'CREATE TABLE customer_payment_applications (id INTEGER, sale_id TEXT, sale_payment_id INT, applied_amount NUMERIC, currency TEXT)',
            'CREATE TABLE supplier_bill (id TEXT, status TEXT, amount NUMERIC, paid_total NUMERIC, balance_due NUMERIC, purchase_id TEXT)',
            'CREATE TABLE supplier_payment (id INTEGER, supplier_bill_id TEXT, amount NUMERIC, reversed_at TEXT)',
        ] as $sql) {
            $this->db->executeStatement($sql);
        }
    }

    public function testACoherentDatabasePassesEveryRule(): void
    {
        $this->db->executeStatement("INSERT INTO sales VALUES ('s1','COMPLETED',NULL,100,40,60,'PEN')");
        $this->db->executeStatement("INSERT INTO sale_payments VALUES (1,'s1',40)");
        $this->db->executeStatement("INSERT INTO customer_account_entries VALUES (1,9,'CHARGE',100,0,100,'sale','s1',NULL), (2,9,'PAYMENT',0,40,60,'sale_payment','1',NULL)");
        $this->db->executeStatement("INSERT INTO customer_payment_applications VALUES (1,'s1',1,40,'PEN')");
        $this->db->executeStatement("INSERT INTO supplier_bill VALUES ('b1','PARTIAL',100,30,70,NULL)");
        $this->db->executeStatement("INSERT INTO supplier_payment VALUES (1,'b1',30,NULL), (2,'b1',50,'2026-11-01')");

        foreach ([...new ReceivablesAudit($this->db)->run(), ...new PayablesAudit($this->db)->run()] as $id => $result) {
            self::assertSame(0, $result['count'], $id);
        }
    }

    public function testInconsistenciesAreReported(): void
    {
        // A credit note applied to s2 explains its lower balance (AR-01 holds there).
        $this->db->executeStatement("INSERT INTO sales VALUES ('s2','COMPLETED',NULL,100,0,70,'PEN')");
        $this->db->executeStatement("INSERT INTO customer_account_entries (id, customer_id, entry_type, debit, credit, running_balance, source, source_id, sale_id) VALUES (9,8,'credit_note',0,30,-30,'credit_note','n1','s2')");
        // AR-01/AR-03: balance and payments disagree with the total.
        $this->db->executeStatement("INSERT INTO sales VALUES ('s1','COMPLETED',NULL,100,0,0,'PEN')");
        $this->db->executeStatement("INSERT INTO sale_payments VALUES (1,'s1',40)");
        // AR-04: the same collection booked twice on the ledger. AR-05: running balance is off.
        $this->db->executeStatement("INSERT INTO customer_account_entries VALUES (1,9,'PAYMENT',0,40,-40,'on_account_collect','c1',NULL), (2,9,'PAYMENT',0,40,-80,'on_account_collect','c1',NULL), (3,9,'ADVANCE',0,10,-10,'x','y',NULL)");
        // AP-01/AP-03: balance does not match payments, status wrong.
        $this->db->executeStatement("INSERT INTO supplier_bill VALUES ('b1','PENDING',100,0,100,'p1'), ('b2','PENDING',100,0,100,'p1')");
        $this->db->executeStatement("INSERT INTO supplier_payment VALUES (1,'b1',30,NULL)");

        $ar = new ReceivablesAudit($this->db)->run();
        $ap = new PayablesAudit($this->db)->run();

        self::assertSame(1, $ar['AR-01']['count']);
        self::assertSame(1, $ar['AR-03']['count']);
        self::assertSame(1, $ar['AR-04']['count']);
        self::assertSame(1, $ar['AR-05']['count']);
        self::assertSame(1, $ap['AP-01']['count']);
        self::assertSame(0, $ap['AP-03']['count']);
        self::assertSame(1, $ap['AP-04']['count']);
    }
}
