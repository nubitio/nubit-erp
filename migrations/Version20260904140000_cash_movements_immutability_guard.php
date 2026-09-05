<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260904140000_cash_movements_immutability_guard extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enforce cash_movements append-only invariant at the DB level with BEFORE UPDATE/DELETE triggers (ADR-004 amendment gap)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_cash_movements_no_update
BEFORE UPDATE ON cash_movements
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'cash_movements is an append-only ledger: UPDATE is not allowed. Insert a correcting movement instead.';
END
SQL);

        $this->addSql(<<<'SQL'
CREATE TRIGGER trg_cash_movements_no_delete
BEFORE DELETE ON cash_movements
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'cash_movements is an append-only ledger: DELETE is not allowed. Insert a correcting movement instead.';
END
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_update');
        $this->addSql('DROP TRIGGER IF EXISTS trg_cash_movements_no_delete');
    }
}
