<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213000000_igv_decimal extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Change movimiento.igv from INT to DECIMAL(4,2) to support fractional rates like 18.00, 10.50, etc.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE movimiento MODIFY igv DECIMAL(4,2) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE movimiento MODIFY igv INT NULL');
    }
}
