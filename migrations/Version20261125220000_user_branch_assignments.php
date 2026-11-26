<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125220000_user_branch_assignments extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store operating branch assignments and the default branch per user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario ADD operating_branch_ids JSON DEFAULT NULL, ADD default_branch_id VARCHAR(36) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP operating_branch_ids, DROP default_branch_id');
    }
}
