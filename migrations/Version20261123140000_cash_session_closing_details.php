<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * What the closing of a shift leaves on record besides the totals: how the cash was counted (bills and coins),
 * whether the opening fund stayed in the drawer, and how much cash was taken out. The closing report reads it back.
 * Shifts closed before this have none of it, and the report simply leaves those lines out.
 */
final class Version20261123140000_cash_session_closing_details extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Record the denomination count and the destination of the cash when a shift closes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_sessions
            ADD counted_denominations JSON DEFAULT NULL,
            ADD opening_fund_kept TINYINT(1) DEFAULT NULL,
            ADD cash_withdrawn_at_close NUMERIC(12, 2) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cash_sessions
            DROP counted_denominations,
            DROP opening_fund_kept,
            DROP cash_withdrawn_at_close');
    }
}
