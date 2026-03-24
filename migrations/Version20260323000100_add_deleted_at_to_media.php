<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds deleted_at to media for soft-delete support.
 */
final class Version20260323000100_add_deleted_at_to_media extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add deleted_at column to media for soft-delete support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media ADD COLUMN deleted_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media DROP COLUMN deleted_at');
    }
}
