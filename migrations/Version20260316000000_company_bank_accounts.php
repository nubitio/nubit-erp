<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260316000000_company_bank_accounts extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add bank and detraction account fields to empresa.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa ADD bank_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD bank_account VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD bank_cci VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE empresa ADD detraccion_account VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE empresa DROP detraccion_account');
        $this->addSql('ALTER TABLE empresa DROP bank_cci');
        $this->addSql('ALTER TABLE empresa DROP bank_account');
        $this->addSql('ALTER TABLE empresa DROP bank_name');
    }
}
