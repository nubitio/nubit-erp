<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820120000SeedAiAssistantFeature extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seed ai_assistant plan feature: disabled on free, enabled on basic and pro.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO plan_features
            (plan_name, feature_key, is_enabled, config_json, created_at, updated_at)
        VALUES
            ('free',  'ai_assistant', 0, NULL, NOW(), NOW()),
            ('basic', 'ai_assistant', 1, NULL, NOW(), NOW()),
            ('pro',   'ai_assistant', 1, NULL, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            is_enabled = VALUES(is_enabled),
            updated_at = NOW()");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM plan_features WHERE feature_key = 'ai_assistant'");
    }
}
