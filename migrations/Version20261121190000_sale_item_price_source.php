<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Records where each sale line's price came from, and what the server expected it to be. */
final class Version20261121190000_sale_item_price_source extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add price_source and list_price_snapshot to sale_items';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_items
            ADD price_source VARCHAR(12) DEFAULT NULL,
            ADD list_price_snapshot NUMERIC(12, 4) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sale_items DROP price_source, DROP list_price_snapshot');
    }
}
