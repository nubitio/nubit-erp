<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Replace two boolean feature flags (feature_webusb_print, feature_local_agent_print)
 * with a single enum config key: print_method = WEBUSB | LOCAL_AGENT.
 *
 * Migration logic:
 *   - If feature_local_agent_print = 'true'  → print_method = 'LOCAL_AGENT'
 *   - Otherwise (webusb or neither)           → print_method = 'WEBUSB'
 * Then delete both old rows.
 */
final class Version20260427000001_print_method_config extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace feature_webusb_print / feature_local_agent_print with print_method enum config';
    }

    public function up(Schema $schema): void
    {
        // Determine the value to migrate to
        $this->addSql(<<<'SQL'
            INSERT INTO config (name, value, type)
            SELECT
                'print_method',
                CASE
                    WHEN EXISTS (
                        SELECT 1 FROM config
                        WHERE name = 'feature_local_agent_print' AND value = 'true'
                    ) THEN 'LOCAL_AGENT'
                    ELSE 'WEBUSB'
                END,
                'string'
            WHERE NOT EXISTS (
                SELECT 1 FROM config WHERE name = 'print_method'
            )
        SQL);

        // Remove the old feature flag rows
        $this->addSql("DELETE FROM config WHERE name IN ('feature_webusb_print', 'feature_local_agent_print')");
    }

    public function down(Schema $schema): void
    {
        // Restore the two boolean flags from the current print_method value
        $this->addSql(<<<'SQL'
            INSERT INTO config (name, value, type)
            SELECT 'feature_webusb_print',
                   CASE WHEN value = 'WEBUSB' THEN 'true' ELSE 'false' END,
                   'bool'
            FROM config WHERE name = 'print_method'
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO config (name, value, type)
            SELECT 'feature_local_agent_print',
                   CASE WHEN value = 'LOCAL_AGENT' THEN 'true' ELSE 'false' END,
                   'bool'
            FROM config WHERE name = 'print_method'
            ON DUPLICATE KEY UPDATE value = VALUES(value)
        SQL);

        $this->addSql("DELETE FROM config WHERE name = 'print_method'");
    }
}
