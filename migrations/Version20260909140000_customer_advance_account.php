<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #383 / epic #367: the chart account a customer advance is parked in.
 *
 * PCGE calls this `122 Anticipos de clientes`. We keep it as a LIABILITY (an
 * obligation to deliver goods later) parented to `42` so balance-sheet rollups
 * treat it as a payable, rather than a contra-asset under `12`.
 */
final class Version20260909140000_customer_advance_account extends AbstractMigration
{
    private const string CODE = '122';

    public function getDescription(): string
    {
        return 'Add chart account 122 Anticipos de clientes (#383)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO chart_of_accounts (id, code, name, type, is_header, parent_code, is_active)
             SELECT UUID(), :insert_code, 'Anticipos de clientes', 'LIABILITY', 0, '42', 1
             FROM DUAL
             WHERE NOT EXISTS (SELECT 1 FROM chart_of_accounts WHERE code = :guard_code)",
            ['insert_code' => self::CODE, 'guard_code' => self::CODE],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM chart_of_accounts WHERE code = :code', ['code' => self::CODE]);
    }
}
