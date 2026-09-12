<?php

declare(strict_types=1);

namespace App\Tests\Identity\Migration;

use PHPUnit\Framework\TestCase;

final class IdentityUserBranchAccessBackfillMigrationTest extends TestCase
{
    private const string MIGRATION =
        __DIR__ . '/../../../migrations/Version20260911100000_backfill_identity_user_branch_access.php';

    public function testBackfillIsScopedToTheTenantCompanyAndSkipsDeletedUsers(): void
    {
        $sql = file_get_contents(self::MIGRATION);
        static::assertIsString($sql);
        static::assertStringContainsString('u.deleted_at IS NULL', $sql);
        static::assertStringContainsString('b.empresa_id', $sql);
        static::assertStringContainsString('FROM empresa c', $sql);
        static::assertStringContainsString('existing.user_id = u.usuario_id', $sql);
        static::assertStringContainsString('existing.branch_id = b.local_id', $sql);
    }

    public function testDownDoesNotDeleteMembershipsCreatedOrChangedAfterTheBackfill(): void
    {
        $sql = file_get_contents(self::MIGRATION);
        static::assertIsString($sql);
        static::assertStringContainsString('deliberately not reversible', $sql);
        static::assertStringNotContainsString('DELETE FROM identity_user_branch_access', $sql);
    }
}
