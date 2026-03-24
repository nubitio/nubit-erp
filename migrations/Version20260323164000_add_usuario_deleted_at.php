<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260323164000_add_usuario_deleted_at extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to usuario for soft-delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP COLUMN deleted_at');
    }
}
