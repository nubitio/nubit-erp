<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127120000_customer_payment_application_batch extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Batch id on customer payment applications (a retried advance application returns the first result)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE customer_payment_applications ADD batch_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_customer_payment_application_batch ON customer_payment_applications (batch_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_customer_payment_application_batch ON customer_payment_applications');
        $this->addSql('ALTER TABLE customer_payment_applications DROP batch_id');
    }
}
