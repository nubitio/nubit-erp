<?php

declare(strict_types=1);

namespace DoctrineMigrationsControlPlane;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260820150000AiAssistantTokenCap extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add monthlyTokenCap config to ai_assistant plan features.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE plan_features SET config_json = '{\"monthlyTokenCap\": 200000, \"modelTier\": \"chat\"}', updated_at = NOW() WHERE feature_key = 'ai_assistant' AND plan_name = 'basic'");
        $this->addSql("UPDATE plan_features SET config_json = '{\"monthlyTokenCap\": 1000000, \"modelTier\": \"chat\"}', updated_at = NOW() WHERE feature_key = 'ai_assistant' AND plan_name = 'pro'");
        $this->addSql("UPDATE plan_features SET config_json = '{\"monthlyTokenCap\": 0, \"modelTier\": \"chat\"}', updated_at = NOW() WHERE feature_key = 'ai_assistant' AND plan_name = 'free'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE plan_features SET config_json = NULL, updated_at = NOW() WHERE feature_key = 'ai_assistant'");
    }
}
