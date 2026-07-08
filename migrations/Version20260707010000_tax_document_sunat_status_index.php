<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260707010000_tax_document_sunat_status_index extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing index on tax_document.sunat_status, a hot filter column with no coverage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_tax_document_sunat_status ON tax_document (sunat_status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tax_document_sunat_status ON tax_document');
    }
}
