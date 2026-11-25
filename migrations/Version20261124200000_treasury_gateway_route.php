<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124200000_treasury_gateway_route extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE treasury_gateway_route (
                branch_id CHAR(36) NOT NULL,
                payment_method_id INT NOT NULL,
                currency CHAR(3) NOT NULL,
                account_id CHAR(36) NOT NULL,
                ledger_code VARCHAR(16) NOT NULL,
                PRIMARY KEY (branch_id, payment_method_id, currency),
                CONSTRAINT fk_treasury_gateway_route_account FOREIGN KEY (account_id) REFERENCES treasury_account (id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((bool) $this->connection->fetchOne('SELECT 1 FROM treasury_gateway_route LIMIT 1'), 'Remove gateway routes before rolling back.');
        $this->addSql('DROP TABLE treasury_gateway_route');
    }
}
