<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305110000_backfill_sale_serie_numero extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill sales.serie and sales.numero from movimiento, then seed sale_sequences with high-water marks. Idempotent — safe to re-run.';
    }

    public function up(Schema $schema): void
    {
        // Step 1 — Backfill sales.serie and sales.numero from the legacy movimiento table.
        // WHERE guard ensures idempotency: only rows where serie is still NULL are updated.
        $this->addSql(<<<'SQL'
UPDATE sales s
INNER JOIN movimiento m ON m.movimiento_id = s.legacy_movimiento_id
SET s.serie  = m.serie,
    s.numero = m.numero
WHERE s.serie IS NULL
  AND m.serie IS NOT NULL
  AND m.serie != ''
SQL);

        // Step 2 — Seed sale_sequences from movimiento high-water marks.
        // ON DUPLICATE KEY UPDATE with GREATEST() prevents overwriting a higher counter
        // if some sequences were already seeded by new sales after this migration window.
        $this->addSql(<<<'SQL'
INSERT INTO sale_sequences (serie, last_number)
SELECT   serie,
         MAX(CAST(numero AS UNSIGNED))
FROM     movimiento
WHERE    serie  IS NOT NULL
  AND    serie  != ''
  AND    numero REGEXP '^[0-9]+$'
GROUP BY serie
ON DUPLICATE KEY UPDATE
    last_number = GREATEST(last_number, VALUES(last_number))
SQL);
    }

    public function down(Schema $schema): void
    {
        // Data rollback not supported; run in a maintenance window only.
        $this->addSql('-- Data rollback not supported; run in a maintenance window only.');
    }
}
