<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fix goods_receipt seed — previous migration omitted required FK columns.
 */
final class Version20260629000003_taxes_seed_fix extends AbstractMigration
{
    private const string BRANCH = '64d2d666-c9f2-11e9-8dee-5600005a2745';
    private const string WAREHOUSE = '9006ad6a-c9f2-11e9-8dee-5600005a2745';
    private const string SUPPLIER = '019cb984-035e-79f3-a269-e728197dd260';

    public function getDescription(): string
    {
        return 'Fix goods_receipts seed with required FK columns';
    }

    public function up(Schema $schema): void
    {
        $now = '2026-06-29 00:00:00';

        // Clean up the failed INSERT IGNORE records (they created empty rows)
        $this->addSql("DELETE FROM goods_receipts WHERE id LIKE 'taxes-seed-%'");

        $purchases = [
            ['id' => 'taxes-seed-gr-001', 'sub' => 15000.00, 'tax' => 2700.00, 'total' => 17700.00, 'date' => '2026-06-08', 'series' => 'F', 'num' => '1500'],
            ['id' => 'taxes-seed-gr-002', 'sub' => 22000.00, 'tax' => 3960.00, 'total' => 25960.00, 'date' => '2026-06-15', 'series' => 'F', 'num' => '1501'],
            ['id' => 'taxes-seed-gr-003', 'sub' => 15000.00, 'tax' => 2700.00, 'total' => 17700.00, 'date' => '2026-06-22', 'series' => 'F', 'num' => '1502'],
        ];

        foreach ($purchases as $p) {
            $this->addSql(
                "INSERT INTO goods_receipts (id, branch_id, warehouse_id, supplier_id, buyer_user_id, status, currency, subtotal, tax_total, grand_total, issued_at, supplier_doc_type, supplier_doc_series, supplier_doc_number, created_at, updated_at)
                 SELECT :id, :branch, :warehouse, :supplier, 1, 'POSTED', 'PEN', :sub, :tax, :total, :date, '01', :series, :num, :now, :now
                 WHERE EXISTS (SELECT 1 FROM local WHERE local_id = :branch)
                   AND EXISTS (SELECT 1 FROM almacen WHERE almacen_id = :warehouse)
                   AND EXISTS (SELECT 1 FROM proveedor WHERE proveedor_id = :supplier)
                   AND EXISTS (SELECT 1 FROM usuario WHERE usuario_id = 1)",
                [
                    'id' => $p['id'],
                    'branch' => self::BRANCH,
                    'warehouse' => self::WAREHOUSE,
                    'supplier' => self::SUPPLIER,
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
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM goods_receipts WHERE id LIKE 'taxes-seed-%'");
    }
}
