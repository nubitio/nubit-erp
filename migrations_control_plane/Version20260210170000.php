<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260210170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add plans table and plan column to tenants';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plans (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(32) NOT NULL,
            limits_json JSON NOT NULL,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE INDEX UNIQ_PLAN_NAME (name),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $now = date('Y-m-d H:i:s');
        $this->addSql(
            'INSERT INTO plans (name, limits_json, is_default, created_at, updated_at) VALUES (?, ?, 1, ?, ?)',
            ['free', json_encode(['users' => 3, 'customers' => 100, 'products' => 50, 'invoices' => 500, 'branches' => 1, 'storage_mb' => 100]), $now, $now],
        );
        $this->addSql(
            'INSERT INTO plans (name, limits_json, is_default, created_at, updated_at) VALUES (?, ?, 0, ?, ?)',
            ['basic', json_encode(['users' => 10, 'customers' => 1000, 'products' => 500, 'invoices' => 5000, 'branches' => 5, 'storage_mb' => 1000]), $now, $now],
        );
        $this->addSql(
            'INSERT INTO plans (name, limits_json, is_default, created_at, updated_at) VALUES (?, ?, 0, ?, ?)',
            ['pro', json_encode(['users' => -1, 'customers' => -1, 'products' => -1, 'invoices' => -1, 'branches' => -1, 'storage_mb' => 10000]), $now, $now],
        );

        $this->addSql("ALTER TABLE tenants ADD COLUMN plan VARCHAR(32) NOT NULL DEFAULT 'free'");
        $this->addSql('ALTER TABLE tenants ADD CONSTRAINT FK_TENANTS_PLAN FOREIGN KEY (plan) REFERENCES plans (name) ON UPDATE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP FOREIGN KEY FK_TENANTS_PLAN');
        $this->addSql('ALTER TABLE tenants DROP COLUMN plan');
        $this->addSql('DROP TABLE plans');
    }
}
