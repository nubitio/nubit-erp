<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\\DBAL\\Schema\\Schema;
use Doctrine\\Migrations\\AbstractMigration;

final class Version20260906030000_retire_cash_movements_triggers extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the retired cash_movements database triggers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_update');
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_delete');
    }

    public function down(Schema $schema): void
    {
        // The retired trigger implementation is intentionally not restored.
    }
}
