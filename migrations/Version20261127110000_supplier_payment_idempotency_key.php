<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127110000_supplier_payment_idempotency_key extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Idempotency key on supplier payments (a retried payment returns the first one)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE supplier_payment ADD idempotency_key VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_supplier_payment_idempotency_key ON supplier_payment (idempotency_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_supplier_payment_idempotency_key ON supplier_payment');
        $this->addSql('ALTER TABLE supplier_payment DROP idempotency_key');
    }
}
