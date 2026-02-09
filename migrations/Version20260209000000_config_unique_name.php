<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209000000_config_unique_name extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique index on config.name to ensure idempotent upserts.';
    }

    public function up(Schema $schema): void
    {
        // Remove duplicate rows keeping the latest value per name
        $this->addSql('
            DELETE c1 FROM config c1
            INNER JOIN config c2
            ON c1.name = c2.name AND c1.id < c2.id
        ');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_D48A2F7C5E237E06 ON config (name)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_D48A2F7C5E237E06 ON config');
    }
}
