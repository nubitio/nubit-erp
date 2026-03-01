<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260227190000_tax_document_exchange_rate extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add exchange_rate to tax_document and backfill from sales V2.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "ALTER TABLE tax_document
             ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(14,6) DEFAULT NULL
             AFTER currency"
        );

        $this->addSql(
            <<<'SQL'
UPDATE tax_document td
LEFT JOIN sales s
       ON s.id = td.movimiento_id
       OR s.legacy_movimiento_id = td.movimiento_id
SET td.exchange_rate = CASE
    WHEN td.currency = 'PEN' THEN 1.000000
    ELSE COALESCE(s.exchange_rate, td.exchange_rate)
END
WHERE td.exchange_rate IS NULL
SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_document DROP COLUMN IF EXISTS exchange_rate');
    }
}
