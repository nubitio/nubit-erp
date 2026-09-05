<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904120000_payment_method_settlement_behavior extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Classify payment catalogue options as collected money or receivable allocations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE payment_method ADD settlement_behavior VARCHAR(16) NOT NULL DEFAULT 'COLLECTED'");
        $this->addSql("UPDATE payment_method SET settlement_behavior = 'RECEIVABLE' WHERE id = 2 OR LOWER(TRIM(name)) IN ('credito', 'crédito')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_method DROP settlement_behavior');
    }
}
