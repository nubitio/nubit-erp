<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Required by the nubitio/admin-bundle 0.15 -> 1.0 upgrade: 1.0 records where
 * a refresh token was issued from and when it was last used, so a person can
 * review their own active sessions. Skipping this makes login answer 401 with
 * a generic credentials message instead of a schema error — see
 * https://github.com/nubitio/nubit-symfony/blob/v1.0.1/packages/admin-bundle/UPGRADE-1.0.md
 */
final class Version20260917160000_refresh_token_session_metadata extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add session metadata columns to nubit_refresh_token (admin-bundle 1.0 upgrade)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_refresh_token ADD user_agent VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE nubit_refresh_token ADD ip_address VARCHAR(45) DEFAULT NULL');
        $this->addSql('ALTER TABLE nubit_refresh_token ADD last_used_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE nubit_refresh_token DROP user_agent');
        $this->addSql('ALTER TABLE nubit_refresh_token DROP ip_address');
        $this->addSql('ALTER TABLE nubit_refresh_token DROP last_used_at');
    }
}
