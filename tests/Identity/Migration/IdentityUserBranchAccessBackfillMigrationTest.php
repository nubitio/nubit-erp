<?php

declare(strict_types=1);

namespace App\Tests\Identity\Migration;

use PHPUnit\Framework\TestCase;

final class IdentityUserBranchAccessBackfillMigrationTest extends TestCase
{
    private const string MIGRATION = __DIR__ . '/../../../migrations/Version20260911100000_backfill_identity_user_branch_access.php';

    public function testBackfillIsScopedToTheTenantCompanyAndSkipsDeletedUsers(): void
    {
        $sql = file_get_contents(self::MIGRATION);
        self::assertIsString($sql);
        self::assertStringContainsString('u.deleted_at IS NULL', $sql);
        self::assertStringContainsString('b.empresa_id', $sql);
        self::assertStringContainsString('FROM empresa c', $sql);
        self::assertStringContainsString('existing.user_id = u.usuario_id', $sql);
        self::assertStringContainsString('existing.branch_id = b.local_id', $sql);
    }

    public function testDownDoesNotDeleteMembershipsCreatedOrChangedAfterTheBackfill(): void
    {
        $sql = file_get_contents(self::MIGRATION);
        self::assertIsString($sql);
        self::assertStringContainsString('deliberately not reversible', $sql);
        self::assertStringNotContainsString('DELETE FROM identity_user_branch_access', $sql);
    }
}
