<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707000001_branch_is_active extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_active flag to branches (local) so closed branches can be disabled for new operations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local ADD is_active TINYINT(1) DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE local DROP COLUMN is_active');
    }
}
