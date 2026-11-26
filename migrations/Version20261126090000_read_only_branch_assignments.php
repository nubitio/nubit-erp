<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126090000_read_only_branch_assignments extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store explicit read-only branch assignments on users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD read_only_branch_ids JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP read_only_branch_ids');
    }
}
