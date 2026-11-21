<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pending invitations are a view of the Usuarios page, so they no longer have a sidebar entry of
 * their own under "Administración".
 */
final class Version20261121180000_merge_invitations_into_users_menu extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the Invitaciones sidebar entry; it is a view of Usuarios';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM menu_rol WHERE menu_id = 842');
        $this->addSql('DELETE FROM menu WHERE id = 842');
        $this->addSql('UPDATE menu SET order_index = 1 WHERE id = 843');
        $this->addSql('UPDATE menu SET order_index = 2 WHERE id = 844');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (842, 1, 'Invitaciones', 'route', '/config/invitations', 'ph ph-envelope-simple', 840, 1)
             ON DUPLICATE KEY UPDATE key_name = VALUES(key_name)"
        );
        $this->addSql('UPDATE menu SET order_index = 2 WHERE id = 843');
        $this->addSql('UPDATE menu SET order_index = 3 WHERE id = 844');
        $this->addSql('INSERT IGNORE INTO menu_rol (menu_id, role_id) SELECT 842, rol_id FROM rol');
    }
}
