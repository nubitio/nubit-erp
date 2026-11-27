<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Undoing a collection is its own right, not implied by collecting (T1.5). Granted to the administrator and to the
 * roles that already may void sales; everyone else gets it from the role editor.
 */
final class Version20261127140000_collection_reversal_permission extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Grant sales.collect_reverse to the administrator and to roles that can void sales';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT IGNORE INTO role_permission (role_id, permission) SELECT DISTINCT role_id, 'sales.collect_reverse' FROM role_permission WHERE permission = 'sales.void'",
        );
        $this->addSql(
            "INSERT IGNORE INTO role_permission (role_id, permission) SELECT 2, 'sales.collect_reverse' WHERE EXISTS (SELECT 1 FROM rol WHERE rol_id = 2)",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'sales.collect_reverse'");
    }
}
