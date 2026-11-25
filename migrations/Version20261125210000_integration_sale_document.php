<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125210000_integration_sale_document extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_sale_document (
                sale_id CHAR(36) NOT NULL PRIMARY KEY,
                document_id CHAR(36) NOT NULL,
                UNIQUE KEY uniq_integration_sale_document (document_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->abortIf(false !== $this->connection->fetchOne('SELECT 1 FROM integration_sale_document LIMIT 1'), 'Keep fiscal requests and receipts traceable.');
        $this->addSql('DROP TABLE integration_sale_document');
    }
}
