<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260911100000_backfill_identity_user_branch_access extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill user branch access from the existing tenant company relation without crossing tenant boundaries.';
    }

    public function up(Schema $schema): void
    {
        // Before explicit membership existed, every non-deleted tenant user could
        // select every branch belonging to the tenant company. Reproduce exactly
        // that legacy grant, while remaining safe to run more than once.
        $this->addSql(<<<'SQL'
            INSERT INTO identity_user_branch_access (user_id, branch_id)
            SELECT u.usuario_id, b.local_id
            FROM usuario u
            CROSS JOIN empresa c
            INNER JOIN local b ON b.empresa_id = c.empresa_id
            WHERE u.deleted_at IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM identity_user_branch_access existing
                  WHERE existing.user_id = u.usuario_id
                    AND existing.branch_id = b.local_id
              )
        SQL);
    }

    public function down(Schema $schema): void
    {
        // The backfill is deliberately not reversible: membership rows may have
        // been edited after deployment, so deleting them would revoke access.
    }
}
