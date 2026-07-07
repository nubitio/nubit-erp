<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260706000001_role_permissions extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add role_permission table for granular RBAC grants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE role_permission (
                id INT AUTO_INCREMENT NOT NULL,
                role_id INT NOT NULL,
                permission VARCHAR(100) NOT NULL,
                UNIQUE INDEX uniq_role_permission (role_id, permission),
                INDEX idx_role_permission_role (role_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_role_permission_role FOREIGN KEY (role_id) REFERENCES rol (rol_id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS role_permission');
    }
}
