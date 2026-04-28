<?php

declare(strict_types=1);

namespace App\Tests\Inventory\Core\Service;

use App\Catalog\Entity\Product;
use App\Inventory\Core\Service\RoleBasedStockAuthorization;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RoleBasedStockAuthorization.
 *
 * Verifies that only actors with the correct roles can override STRICT stock policy.
 */
final class RoleBasedStockAuthorizationTest extends TestCase
{
    private RoleBasedStockAuthorization $auth;
    private Product $product;

    protected function setUp(): void
    {
        $this->auth    = new RoleBasedStockAuthorization();
        $this->product = new Product();
    }

    // ─── Granted cases ───────────────────────────────────────────────────────────

    public function testSuperAdminIsGranted(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-1',
            ['ROLE_USER', 'ROLE_SUPER_ADMIN'],
        );

        self::assertTrue($result->granted);
        self::assertNull($result->reason);
    }

    public function testAdminIsGranted(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-2',
            ['ROLE_ADMIN'],
        );

        self::assertTrue($result->granted);
    }

    public function testInventoryOverrideRoleIsGranted(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-3',
            ['ROLE_USER', 'ROLE_INVENTORY_OVERRIDE'],
        );

        self::assertTrue($result->granted);
    }

    // ─── Denied cases ────────────────────────────────────────────────────────────

    public function testRegularUserIsDenied(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-4',
            ['ROLE_USER'],
        );

        self::assertFalse($result->granted);
        self::assertNotNull($result->reason);
        self::assertStringContainsString('actor-4', (string) $result->reason);
    }

    public function testEmptyRolesIsDenied(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-5',
            [],
        );

        self::assertFalse($result->granted);
    }

    public function testCaseSensitiveRoleMatchDeniesLowercase(): void
    {
        // Roles are case-sensitive; 'role_admin' must not match 'ROLE_ADMIN'
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-6',
            ['role_admin'],
        );

        self::assertFalse($result->granted);
    }

    // ─── Denial message content ──────────────────────────────────────────────────

    public function testDenialReasonListsAllOverrideRoles(): void
    {
        $result = $this->auth->canOverrideStrictPolicy(
            $this->product,
            'actor-7',
            ['ROLE_USER'],
        );

        self::assertFalse($result->granted);
        self::assertStringContainsString('ROLE_INVENTORY_OVERRIDE', (string) $result->reason);
        self::assertStringContainsString('ROLE_SUPER_ADMIN', (string) $result->reason);
        self::assertStringContainsString('ROLE_ADMIN', (string) $result->reason);
    }

    // ─── Product argument is intentionally unused ────────────────────────────────

    public function testProductArgumentDoesNotAffectOutcome(): void
    {
        // The implementation ignores $product (reserved for future product-specific overrides).
        // Two calls with different products but same roles must return the same result.
        $product2 = new Product();

        $resultA = $this->auth->canOverrideStrictPolicy($this->product, 'actor-8', ['ROLE_USER']);
        $resultB = $this->auth->canOverrideStrictPolicy($product2, 'actor-8', ['ROLE_USER']);

        self::assertSame($resultA->granted, $resultB->granted);
    }
}
