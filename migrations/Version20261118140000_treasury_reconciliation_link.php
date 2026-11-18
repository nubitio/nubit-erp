<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Lets a bank statement line be reconciled against a Treasury movement (ADR-021 wave 3). The old links to
 * supplier payments, sale payments and cash movements stay until those paths are retired. The column uses the
 * same collation as treasury_movement.id so the two can be compared without a cast.
 */
final class Version20261118140000_treasury_reconciliation_link extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add treasury_movement_id to bank_reconciliation_match';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_reconciliation_match ADD treasury_movement_id CHAR(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_brm_treasury_movement ON bank_reconciliation_match (treasury_movement_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_brm_treasury_movement ON bank_reconciliation_match');
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP COLUMN treasury_movement_id');
    }
}
