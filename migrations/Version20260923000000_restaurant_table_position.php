<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-18 follow-up: lets the owner place each table on the floor-plan editor's
 * canvas. Nullable — existing tables simply appear "unplaced" until dragged.
 */
final class Version20260923000000_restaurant_table_position extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add position_x/position_y to restaurant_tables for the floor-plan editor';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant_tables
                ADD position_x DOUBLE PRECISION DEFAULT NULL,
                ADD position_y DOUBLE PRECISION DEFAULT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant_tables
                DROP position_x,
                DROP position_y
        SQL);
    }
}
