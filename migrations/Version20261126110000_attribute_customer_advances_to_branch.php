<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126110000_attribute_customer_advances_to_branch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the operating branch when receiving a customer advance';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_account_entries ADD branch_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_account_entries DROP branch_id');
    }
}
