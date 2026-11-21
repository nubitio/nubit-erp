<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Settings are reached from the user menu ("Ajustes"), so the sidebar's "Configuración" group goes:
 * "Preferencias generales" (/config), "Migración inicial" (/setup/import, now a link inside the
 * settings dialog) and the group that held them.
 */
final class Version20261121150000_remove_config_menu extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove the sidebar Configuración group; settings live in the user menu';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM menu_rol WHERE menu_id IN (908, 820, 909)');
        // Children first: the self-referencing FK on parent_id rejects removing a parent in use.
        $this->addSql('DELETE FROM menu WHERE id IN (908, 820)');
        $this->addSql('DELETE FROM menu WHERE id = 909');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (909, 13, 'Configuración', 'group', NULL, 'ph ph-gear-six', NULL, 1)
             ON DUPLICATE KEY UPDATE key_name = VALUES(key_name)"
        );
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (908, 0, 'Preferencias generales', 'route', '/config', 'ph ph-sliders-horizontal', 909, 1),
                    (820, 1, 'Migración inicial', 'route', '/setup/import', 'ph ph-database', 909, 1)
             ON DUPLICATE KEY UPDATE key_name = VALUES(key_name)"
        );
        $this->addSql(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id)
             SELECT m.id, r.rol_id FROM menu m CROSS JOIN rol r WHERE m.id IN (908, 820, 909)'
        );
    }
}
