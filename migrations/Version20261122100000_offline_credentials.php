<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sales made without connection are signed by the till with a key issued to the cashier
 * while online, so the server attributes them to whoever made them, whoever's session
 * delivers them. `sales.synced_by_user_id` records who delivered them when that differs.
 */
final class Version20261122100000_offline_credentials extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add identity_offline_credential and sales.synced_by_user_id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE identity_offline_credential (
            id VARCHAR(36) NOT NULL,
            user_id INT NOT NULL,
            issued_at INT NOT NULL,
            expires_at INT NOT NULL,
            revoked_at INT DEFAULT NULL,
            INDEX IDX_identity_offline_credential_user (user_id, expires_at),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sales ADD synced_by_user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP synced_by_user_id');
        $this->addSql('DROP TABLE identity_offline_credential');
    }
}
