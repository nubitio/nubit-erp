<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260821121000_accounting_role_permission extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant accounting.view to the administrator role.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'INSERT IGNORE INTO role_permission (role_id, permission) SELECT rol_id, ? FROM rol WHERE rol_id IN (1, 2)',
            ['accounting.view'],
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'accounting.view'");
    }
}
