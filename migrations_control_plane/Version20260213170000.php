<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create control_plane_admin_users table for control plane authentication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS control_plane_admin_users (
                id INT UNSIGNED AUTO_INCREMENT NOT NULL,
                username VARCHAR(190) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                roles JSON NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_CP_ADMIN_USERS_USERNAME (username),
                INDEX IDX_CP_ADMIN_USERS_ACTIVE (is_active),
                PRIMARY KEY(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS control_plane_admin_users');
    }
}

