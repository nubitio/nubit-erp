<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260819190000_sale_offline_retry_key extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'sales.offline_retry_key: lets SaleProcessor detect and replay a retried offline-sale '
            . 'submission instead of creating a duplicate sale — purely additive, nullable, unique. '
            . 'Named distinctly from the pre-existing sales.idempotency_key column, which is an '
            . 'unrelated deterministic sync marker (SalesWriteGateway::buildOfflineMeta()) derived '
            . 'from the row\'s own id and therefore never stable across a retry.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales ADD offline_retry_key VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE sales ADD UNIQUE INDEX UNIQ_SALES_OFFLINE_RETRY_KEY (offline_retry_key)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sales DROP INDEX UNIQ_SALES_OFFLINE_RETRY_KEY');
        $this->addSql('ALTER TABLE sales DROP offline_retry_key');
    }
}
