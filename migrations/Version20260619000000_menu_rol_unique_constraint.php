<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Deduplicate menu_rol and add UNIQUE(menu_id, role_id) to prevent future duplicates.
 *
 * The table was created with only an auto-increment PK and two non-unique indexes,
 * so MenuSeedService::seed() and assignFullMenuToRole() could both insert rows for
 * the same (menu_id, role_id) pair without triggering ON DUPLICATE KEY UPDATE.
 * This resulted in every role seeing each menu item twice in the UI.
 */
final class Version20260619000000_menu_rol_unique_constraint extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Deduplicate menu_rol rows and add UNIQUE(menu_id, role_id)';
    }

    public function up(Schema $schema): void
    {
        // 1. Keep only the lowest id for each (menu_id, role_id) pair.
        $this->addSql(
            'DELETE mr FROM menu_rol mr
             INNER JOIN menu_rol mr2
               ON mr.menu_id = mr2.menu_id
              AND mr.role_id = mr2.role_id
              AND mr.id > mr2.id'
        );

        // 2. Add the unique constraint so future inserts cannot create duplicates.
        $this->addSql(
            'ALTER TABLE menu_rol ADD UNIQUE INDEX uniq_menu_rol (menu_id, role_id)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE menu_rol DROP INDEX uniq_menu_rol');
    }
}
