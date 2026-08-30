<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260828090100_cash_rounding_adjustment extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the cash-only rounding adjustment on each sale for reconciliation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sales ADD cash_rounding_adjustment NUMERIC(12, 2) DEFAULT '0.00' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP cash_rounding_adjustment');
    }
}
