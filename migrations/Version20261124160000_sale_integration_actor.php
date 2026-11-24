<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124160000_sale_integration_actor extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow sales attributed to a tenant API client instead of a human seller, without a cash drawer';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE sales
                MODIFY seller_user_id INT DEFAULT NULL,
                ADD integration_client_id VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                ADD INDEX IDX_SALES_INTEGRATION_CLIENT (integration_client_id),
                ADD CONSTRAINT FK_SALES_INTEGRATION_CLIENT FOREIGN KEY (integration_client_id)
                    REFERENCES integration_api_client (id) ON DELETE RESTRICT,
                ADD CONSTRAINT CHK_SALES_ORIGIN_ACTOR CHECK (
                    (seller_user_id IS NOT NULL AND integration_client_id IS NULL)
                    OR (seller_user_id IS NULL AND integration_client_id IS NOT NULL)
                )
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(
            (bool) $this->connection->fetchOne('SELECT 1 FROM sales WHERE seller_user_id IS NULL LIMIT 1'),
            'Cannot make the seller mandatory while sales attributed to API clients exist.',
        );

        $this->addSql(<<<'SQL'
            ALTER TABLE sales
                DROP CONSTRAINT CHK_SALES_ORIGIN_ACTOR,
                DROP FOREIGN KEY FK_SALES_INTEGRATION_CLIENT,
                DROP INDEX IDX_SALES_INTEGRATION_CLIENT,
                DROP integration_client_id,
                MODIFY seller_user_id INT NOT NULL
            SQL);
    }
}
