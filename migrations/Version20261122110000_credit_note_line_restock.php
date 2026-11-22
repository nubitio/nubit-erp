<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Lets a credit note decide, line by line, whether returned goods re-enter stock. */
final class Version20261122110000_credit_note_line_restock extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add restock flag to tax_document_line and create billing_credit_note_draft';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tax_document_line ADD restock TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql('CREATE TABLE billing_credit_note_draft (
            id VARCHAR(36) NOT NULL,
            branch_id VARCHAR(36) NOT NULL,
            user_id INT NOT NULL,
            related_tax_document_id VARCHAR(36) NOT NULL,
            reason_code VARCHAR(2) DEFAULT NULL,
            reason_description VARCHAR(250) DEFAULT NULL,
            line_items JSON NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX IDX_BILLING_CN_DRAFT_OWNER (branch_id, user_id, updated_at),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE billing_credit_note_draft');
        $this->addSql('ALTER TABLE tax_document_line DROP restock');
    }
}
