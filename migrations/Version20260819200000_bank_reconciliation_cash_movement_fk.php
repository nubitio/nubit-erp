<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819200000_bank_reconciliation_cash_movement_fk extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop FK_BRM_CASH_MOVEMENT: bank_reconciliation_match.cash_movement_id can no longer be '
            . 'constrained to the legacy cash_movement (singular) table now that reconciliation reads/writes '
            . 'cash movement ids from the v2 cash_movements (plural) table — see ADR-004\'s amendment. '
            . 'Column, data, and index are untouched; only the FK constraint is removed.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_reconciliation_match DROP FOREIGN KEY FK_BRM_CASH_MOVEMENT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE bank_reconciliation_match '
            . 'ADD CONSTRAINT FK_BRM_CASH_MOVEMENT FOREIGN KEY (cash_movement_id) REFERENCES cash_movement (id)',
        );
    }
}
