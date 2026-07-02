<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260629000002_taxes_seed extends AbstractMigration
{
    private const string BRANCH = '64d2d666-c9f2-11e9-8dee-5600005a2745';

    public function getDescription(): string
    {
        return 'Seed demo tax documents, purchases, and retention/perception data for June 2026';
    }

    public function up(Schema $schema): void
    {
        $now = '2026-06-29 00:00:00';

        // ── Tax documents for June 2026 ────────────────────────────────────

        $docs = [
            [
                'id' => 'taxes-seed-f001-001', 'customer' => 2, 'series' => 'F001', 'number' => '710',
                'date' => '2026-06-05', 'base' => 10000.00, 'igv' => 1800.00, 'total' => 11800.00,
            ],
            [
                'id' => 'taxes-seed-f001-002', 'customer' => 2, 'series' => 'F001', 'number' => '711',
                'date' => '2026-06-10', 'base' => 25000.00, 'igv' => 4500.00, 'total' => 29500.00,
            ],
            [
                'id' => 'taxes-seed-f001-003', 'customer' => 1, 'series' => 'F001', 'number' => '712',
                'date' => '2026-06-15', 'base' => 24200.00, 'igv' => 4356.00, 'total' => 28556.00,
            ],
            [
                'id' => 'taxes-seed-f001-004', 'customer' => 1, 'series' => 'F001', 'number' => '713',
                'date' => '2026-06-18', 'base' => 5000.00, 'igv' => 900.00, 'total' => 5900.00,
                'det_code' => '001', 'det_pct' => 4.00, 'det_amount' => 200.00,
            ],
            [
                'id' => 'taxes-seed-f001-005', 'customer' => 2, 'series' => 'F001', 'number' => '714',
                'date' => '2026-06-20', 'base' => 3200.00, 'igv' => 576.00, 'total' => 3776.00,
                'det_code' => '005', 'det_pct' => 12.00, 'det_amount' => 384.00,
                'deposited' => '2026-06-22 10:00:00',
            ],
            [
                'id' => 'taxes-seed-f001-006', 'customer' => 3, 'series' => 'F001', 'number' => '715',
                'date' => '2026-06-22', 'base' => 2400.00, 'igv' => 432.00, 'total' => 2832.00,
                'det_code' => '001', 'det_pct' => 4.00, 'det_amount' => 96.00,
                'deposited' => '2026-06-24 14:00:00',
            ],
            [
                'id' => 'taxes-seed-f001-007', 'customer' => 1, 'series' => 'F001', 'number' => '716',
                'date' => '2026-06-25', 'base' => 8800.00, 'igv' => 1584.00, 'total' => 10384.00,
                'det_code' => '001', 'det_pct' => 4.00, 'det_amount' => 352.00,
            ],
            [
                'id' => 'taxes-seed-f001-008', 'customer' => 4, 'series' => 'F001', 'number' => '717',
                'date' => '2026-06-28', 'base' => 11400.00, 'igv' => 2052.00, 'total' => 13452.00,
            ],
        ];

        foreach ($docs as $d) {
            $this->addSql(
                "INSERT IGNORE INTO tax_document (tax_document_id, branch_id, customer_id, document_type, series, number, issue_date, currency, subtotal, taxable_amount, igv_amount, total, detraction_code, detraction_percent, detraction_amount, detraction_deposited_at, created_at, updated_at)
                 VALUES (:id, :branch, :customer, '01', :series, :number, :date, 'PEN', :base, :base, :igv, :total, :det_code, :det_pct, :det_amount, :deposited, :now, :now)",
                [
                    'id' => $d['id'],
                    'branch' => self::BRANCH,
                    'customer' => $d['customer'],
                    'series' => $d['series'],
                    'number' => $d['number'],
                    'date' => $d['date'],
                    'base' => $d['base'],
                    'igv' => $d['igv'],
                    'total' => $d['total'],
                    'det_code' => $d['det_code'] ?? null,
                    'det_pct' => $d['det_pct'] ?? null,
                    'det_amount' => $d['det_amount'] ?? null,
                    'deposited' => $d['deposited'] ?? null,
                    'now' => $now,
                ]
            );
        }

        // ── Goods receipts (purchases) for June 2026 ───────────────────────

        $purchases = [
            [
                'id' => 'taxes-seed-gr-001', 'sub' => 15000.00, 'tax' => 2700.00,
                'total' => 17700.00, 'date' => '2026-06-08', 'series' => 'F', 'num' => '1500',
            ],
            [
                'id' => 'taxes-seed-gr-002', 'sub' => 22000.00, 'tax' => 3960.00,
                'total' => 25960.00, 'date' => '2026-06-15', 'series' => 'F', 'num' => '1501',
            ],
            [
                'id' => 'taxes-seed-gr-003', 'sub' => 15000.00, 'tax' => 2700.00,
                'total' => 17700.00, 'date' => '2026-06-22', 'series' => 'F', 'num' => '1502',
            ],
        ];

        foreach ($purchases as $p) {
            $this->addSql(
                "INSERT IGNORE INTO goods_receipts (id, status, subtotal, tax_total, grand_total, issued_at, supplier_doc_type, supplier_doc_series, supplier_doc_number, created_at, updated_at)
                 VALUES (:id, 'POSTED', :sub, :tax, :total, :date, '01', :series, :num, :now, :now)",
                [
                    'id' => $p['id'],
                    'sub' => $p['sub'],
                    'tax' => $p['tax'],
                    'total' => $p['total'],
                    'date' => $p['date'] . ' 00:00:00',
                    'series' => $p['series'],
                    'num' => $p['num'],
                    'now' => $now,
                ]
            );
        }

        // ── Retention / Percepcion records ────────────────────────────────

        $this->addSql(
            "INSERT IGNORE INTO retention_percepcion (id, type, direction, counterparty_name, document_reference, rate, base_amount, amount, period_date, created_at, updated_at)
             VALUES ('taxes-seed-rp-001', 'retencion', 'sufrida', 'Corporacion Lima SAC', 'F001-713', 3.0000, 5900.00, 177.00, '2026-06-18', :now, :now)",
            ['now' => $now]
        );
        $this->addSql(
            "INSERT IGNORE INTO retention_percepcion (id, type, direction, counterparty_name, document_reference, rate, base_amount, amount, period_date, created_at, updated_at)
             VALUES ('taxes-seed-rp-002', 'percepcion', 'sufrida', 'Importaciones del Pacifico', 'F-1180', 2.0000, 1120.00, 22.40, '2026-06-20', :now, :now)",
            ['now' => $now]
        );
        $this->addSql(
            "INSERT IGNORE INTO retention_percepcion (id, type, direction, counterparty_name, document_reference, rate, base_amount, amount, period_date, created_at, updated_at)
             VALUES ('taxes-seed-rp-003', 'retencion', 'efectuada', 'Comercial Torres EIRL', 'F-0902', 3.0000, 760.00, 22.80, '2026-06-25', :now, :now)",
            ['now' => $now]
        );

        // ── Tenant settings detraction account ─────────────────────────────

        $this->addSql(
            "UPDATE tenant_settings SET detraction_account = '00-068-012345' WHERE detraction_account IS NULL"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM tax_document WHERE tax_document_id LIKE 'taxes-seed-%'");
        $this->addSql("DELETE FROM goods_receipts WHERE id LIKE 'taxes-seed-%'");
        $this->addSql("DELETE FROM retention_percepcion WHERE id LIKE 'taxes-seed-%'");
        $this->addSql("UPDATE tenant_settings SET detraction_account = NULL");
    }
}
