<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260903070000_sale_payment_reference extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the bank or operation reference entered for customer payments';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_payments ADD reference VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_payments DROP reference');
    }
}
