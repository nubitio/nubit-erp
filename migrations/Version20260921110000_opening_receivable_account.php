<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260921110000_opening_receivable_account extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Adds the opening-equity account used by imported receivable balances.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO chart_of_accounts (id, code, name, type, is_header, parent_code, is_active) VALUES (UUID(), '591', 'Resultados acumulados / saldos iniciales', 'EQUITY', 0, '50', 1)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM chart_of_accounts WHERE code = '591'");
    }
}
