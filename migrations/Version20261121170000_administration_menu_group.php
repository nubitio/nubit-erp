<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Users, invitations, roles and activity are pages of their own again, grouped in the sidebar under
 * "Administración" instead of tabs of the settings hub. Mirrors MenuSeedService for existing tenants.
 */
final class Version20261121170000_administration_menu_group extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Administración sidebar group (users, invitations, roles, activity)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (840, 15, 'Administración', 'group', NULL, 'ph ph-shield-star', NULL, 1)
             ON DUPLICATE KEY UPDATE key_name = VALUES(key_name)"
        );
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (841, 0, 'Usuarios', 'route', '/config/users', 'ph ph-users', 840, 1),
                    (842, 1, 'Invitaciones', 'route', '/config/invitations', 'ph ph-envelope-simple', 840, 1),
                    (843, 2, 'Roles', 'route', '/config/roles', 'ph ph-user-gear', 840, 1),
                    (844, 3, 'Actividad', 'route', '/config/activity', 'ph ph-clock-counter-clockwise', 840, 1)
             ON DUPLICATE KEY UPDATE key_name = VALUES(key_name)"
        );
        $this->addSql(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id)
             SELECT m.id, r.rol_id FROM menu m CROSS JOIN rol r WHERE m.id IN (840, 841, 842, 843, 844)'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM menu_rol WHERE menu_id IN (840, 841, 842, 843, 844)');
        // Children first: the self-referencing FK on parent_id rejects removing a parent in use.
        $this->addSql('DELETE FROM menu WHERE id IN (841, 842, 843, 844)');
        $this->addSql('DELETE FROM menu WHERE id = 840');
    }
}
