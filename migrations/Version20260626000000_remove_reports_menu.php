<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Reports merged into /dashboard — remove legacy menu entry.
 */
final class Version20260626000000_remove_reports_menu extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove deprecated Reportes menu item (analytics moved to dashboard)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "DELETE mr FROM menu_rol mr
             INNER JOIN menu m ON mr.menu_id = m.id
             WHERE m.route = 'reports'"
        );
        $this->addSql("DELETE FROM menu WHERE route = 'reports'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "INSERT INTO menu (id, order_index, key_name, type, route, icon, parent_id, is_active)
             VALUES (600, 10, 'Reportes', 'route', 'reports', 'ph ph-chart-bar', NULL, 1)
             ON DUPLICATE KEY UPDATE
                order_index = VALUES(order_index),
                key_name = VALUES(key_name),
                type = VALUES(type),
                route = VALUES(route),
                icon = VALUES(icon),
                parent_id = VALUES(parent_id),
                is_active = VALUES(is_active)"
        );
        $this->addSql(
            'INSERT IGNORE INTO menu_rol (menu_id, role_id)
             SELECT 600, r.rol_id FROM rol r'
        );
    }
}