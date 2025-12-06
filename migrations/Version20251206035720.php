<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251206035720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ubigeo (codigo VARCHAR(10) NOT NULL, departamento VARCHAR(255) DEFAULT NULL, provincia VARCHAR(255) DEFAULT NULL, distrito VARCHAR(255) DEFAULT NULL, PRIMARY KEY(codigo)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE empresa ADD ubigeo VARCHAR(255) DEFAULT NULL, ADD departamento VARCHAR(255) DEFAULT NULL, ADD provincia VARCHAR(255) DEFAULT NULL, ADD distrito VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE ubigeo');
        $this->addSql('ALTER TABLE empresa DROP ubigeo, DROP departamento, DROP provincia, DROP distrito');
    }
}
