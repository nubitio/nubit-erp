<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124203000_treasury_gateway_settlement extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_gateway_settlement (
                id CHAR(36) NOT NULL,
                clearing_account_id CHAR(36) NOT NULL,
                reference VARBINARY(120) NOT NULL,
                payload_hash CHAR(64) NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_gateway_settlement_reference (clearing_account_id, reference),
                CONSTRAINT fk_gateway_settlement_clearing FOREIGN KEY (clearing_account_id) REFERENCES treasury_account (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_gateway_settlement_capture (
                movement_id CHAR(36) NOT NULL,
                settlement_id CHAR(36) NOT NULL,
                PRIMARY KEY (movement_id),
                CONSTRAINT fk_gateway_settlement_capture FOREIGN KEY (movement_id) REFERENCES treasury_movement (id),
                CONSTRAINT fk_gateway_settlement_header FOREIGN KEY (settlement_id) REFERENCES treasury_gateway_settlement (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT 1 FROM treasury_gateway_settlement LIMIT 1'), 'Gateway settlements must remain traceable.');
        $this->addSql('DROP TABLE treasury_gateway_settlement_capture');
        $this->addSql('DROP TABLE treasury_gateway_settlement');
    }
}
