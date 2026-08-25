<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260825130000_sale_note extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist internal sale notes in the Sales V2 table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD note TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP note');
    }
}
