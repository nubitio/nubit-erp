<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260626120000_purchase_receipt_number extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add purchase receipt_no sequence for goods_receipts and seed purchase_receipt_sequences.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE purchase_receipt_sequences (
    id TINYINT NOT NULL DEFAULT 1,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
SQL);

        $this->addSql("INSERT INTO purchase_receipt_sequences (id, last_number) VALUES (1, 0)");

        $this->addSql(<<<'SQL'
ALTER TABLE goods_receipts
    ADD receipt_no INT UNSIGNED DEFAULT NULL AFTER id,
    ADD UNIQUE INDEX uq_goods_receipts_receipt_no (receipt_no)
SQL);

        $this->addSql(<<<'SQL'
SET @receipt_no := 0;
UPDATE goods_receipts
SET receipt_no = (@receipt_no := @receipt_no + 1)
WHERE receipt_no IS NULL
ORDER BY COALESCE(created_at, issued_at), id
SQL);

        $this->addSql(<<<'SQL'
UPDATE purchase_receipt_sequences
SET last_number = COALESCE((SELECT MAX(receipt_no) FROM goods_receipts), 0)
WHERE id = 1
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE goods_receipts DROP INDEX uq_goods_receipts_receipt_no, DROP COLUMN receipt_no');
        $this->addSql('DROP TABLE purchase_receipt_sequences');
    }
}