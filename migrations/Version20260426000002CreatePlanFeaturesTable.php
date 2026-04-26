<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260426000002CreatePlanFeaturesTable extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create plan_features table with FK to plans and UNIQUE(plan_name, feature_key).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE plan_features (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            plan_name   VARCHAR(32)  NOT NULL,
            feature_key VARCHAR(64)  NOT NULL,
            is_enabled  TINYINT(1)   NOT NULL DEFAULT 0,
            config_json JSON         NULL,
            created_at  DATETIME     NOT NULL,
            updated_at  DATETIME     NOT NULL,
            UNIQUE KEY uq_plan_feature (plan_name, feature_key),
            CONSTRAINT fk_plan_features_plan
                FOREIGN KEY (plan_name) REFERENCES plans(name)
                ON UPDATE CASCADE ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS plan_features');
    }
}
