<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Records who voided a sale (and when/why) and who issued a tax document.
 *
 * Purely additive and nullable: rows written before this migration keep NULL authors on purpose,
 * because the real author was never stored and must not be guessed.
 */
final class Version20261122120000_record_void_and_credit_note_author extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add voided_by_user_id/voided_at/void_reason to sales and created_by_user_id to tax_document';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE sales
                ADD voided_by_user_id INT DEFAULT NULL,
                ADD voided_at DATETIME DEFAULT NULL,
                ADD void_reason VARCHAR(255) DEFAULT NULL,
                ADD INDEX IDX_SALES_VOIDED (voided_at, voided_by_user_id)',
        );
        $this->addSql(
            'ALTER TABLE tax_document
                ADD created_by_user_id INT DEFAULT NULL,
                ADD INDEX IDX_TAX_DOCUMENT_CREATED_BY (created_by_user_id, created_at)',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_document DROP INDEX IDX_TAX_DOCUMENT_CREATED_BY, DROP created_by_user_id');
        $this->addSql(
            'ALTER TABLE sales
                DROP INDEX IDX_SALES_VOIDED,
                DROP voided_by_user_id,
                DROP voided_at,
                DROP void_reason',
        );
    }
}
