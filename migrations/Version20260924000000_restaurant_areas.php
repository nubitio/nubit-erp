<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * NB-18 follow-up: promotes the free-text `restaurant_tables.area` into its
 * own `restaurant_areas` entity (name + floor + display order), so a
 * restaurant with several floors/areas can show one tab per area on the
 * floor-plan editor and the operational table map. Existing distinct area
 * values are migrated into floor 1, in the order they first appear.
 */
final class Version20260924000000_restaurant_areas extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extract restaurant_tables.area into a proper restaurant_areas table (floor + order)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE restaurant_areas (
                id INT AUTO_INCREMENT NOT NULL,
                name VARCHAR(64) NOT NULL,
                floor INT NOT NULL,
                order_index INT NOT NULL,
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            INSERT INTO restaurant_areas (name, floor, order_index)
            SELECT DISTINCT area, 1, 0 FROM restaurant_tables
        SQL);

        $this->addSql('ALTER TABLE restaurant_tables ADD area_id INT DEFAULT NULL');

        $this->addSql(<<<'SQL'
            UPDATE restaurant_tables rt
            INNER JOIN restaurant_areas ra ON ra.name = rt.area
            SET rt.area_id = ra.id
        SQL);

        $this->addSql('ALTER TABLE restaurant_tables MODIFY area_id INT NOT NULL');
        $this->addSql('ALTER TABLE restaurant_tables DROP area');
        $this->addSql(<<<'SQL'
            ALTER TABLE restaurant_tables
                ADD CONSTRAINT FK_restaurant_tables_area
                FOREIGN KEY (area_id) REFERENCES restaurant_areas (id)
        SQL);
        $this->addSql('CREATE INDEX idx_restaurant_table_area ON restaurant_tables (area_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE restaurant_tables ADD area VARCHAR(64) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE restaurant_tables rt
            INNER JOIN restaurant_areas ra ON ra.id = rt.area_id
            SET rt.area = ra.name
        SQL);
        $this->addSql('ALTER TABLE restaurant_tables MODIFY area VARCHAR(64) NOT NULL');
        $this->addSql('ALTER TABLE restaurant_tables DROP FOREIGN KEY FK_restaurant_tables_area');
        $this->addSql('DROP INDEX idx_restaurant_table_area ON restaurant_tables');
        $this->addSql('ALTER TABLE restaurant_tables DROP area_id');
        $this->addSql('DROP TABLE restaurant_areas');
    }
}
