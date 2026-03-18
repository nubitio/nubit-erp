<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds a cdr_path column to tax_document.
 *
 * Stores the S3 key (without tenant prefix) of the CDR zip file returned by
 * SUNAT after a successful send or consult operation.  The column is nullable
 * so that existing rows (which had their CDR path derived at runtime) are not
 * affected; the path will be populated the next time a send/consult runs for
 * each document.
 */
final class Version20260318000001_add_cdr_path_to_tax_document extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add cdr_path column to tax_document to persist the S3 key of the CDR zip';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tax_document ADD COLUMN cdr_path VARCHAR(255) DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE tax_document DROP COLUMN cdr_path");
    }
}
