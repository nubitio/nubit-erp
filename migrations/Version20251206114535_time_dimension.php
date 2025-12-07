<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206114535_time_dimension extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE time_dimension (id INT NOT NULL, date DATE DEFAULT NULL, year INT DEFAULT NULL, month INT DEFAULT NULL, day INT DEFAULT NULL, quarter INT DEFAULT NULL, week INT DEFAULT NULL, day_name VARCHAR(255) DEFAULT NULL, month_name VARCHAR(255) DEFAULT NULL, holiday TINYINT(1) DEFAULT 0, weekend TINYINT(1) DEFAULT 0, event VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE time_dimension');
    }
}
