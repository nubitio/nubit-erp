<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110090000_pos_piece_count_setting extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the per-tenant switch for the POS piece counter on weighted lines';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings ADD pos_piece_count_enabled TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenant_settings DROP pos_piece_count_enabled');
    }
}
