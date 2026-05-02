<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Identity\Dto\AuthUserDto;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Inventory\Core\Enum\StockPolicy;
use App\Sales\State\NegativeStockPolicyResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class NegativeStockPolicyResolverTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private NegativeStockPolicyResolver $resolver;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->resolver = new NegativeStockPolicyResolver($this->settings);
    }

    // ── resolvePolicy() — returns StockPolicy enum ───────────────────────────

    #[DataProvider('stockPolicyMappingProvider')]
    public function testResolvePolicyMapsRawValuesToEnum(string $raw, StockPolicy $expected): void
    {
        $this->settings->method('get')
            ->with(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')
            ->willReturn($raw);

        self::assertSame($expected, $this->resolver->resolvePolicy());
    }

    /** @return array<string, array{string, StockPolicy}> */
    public static function stockPolicyMappingProvider(): array
    {
        return [
            // Canonical new values
            'STRICT'                      => ['STRICT',          StockPolicy::STRICT],
            'WARNING_ONLY'                => ['WARNING_ONLY',    StockPolicy::WARNING_ONLY],
            'ALLOW_NEGATIVE'              => ['ALLOW_NEGATIVE',  StockPolicy::ALLOW_NEGATIVE],
            // Legacy synonym values (kept for backwards compat with existing DB rows)
            'BLOCK synonym'               => ['BLOCK',           StockPolicy::STRICT],
            'ALLOW_WITH_ROLE synonym'     => ['ALLOW_WITH_ROLE', StockPolicy::WARNING_ONLY],
            'ALLOW_ALL synonym'           => ['ALLOW_ALL',       StockPolicy::ALLOW_NEGATIVE],
            // Case normalisation
            'strict lowercase'            => ['strict',          StockPolicy::STRICT],
            'allow_negative lowercase'    => ['allow_negative',  StockPolicy::ALLOW_NEGATIVE],
            // Unknown / empty → safe default
            'empty string → STRICT'       => ['',               StockPolicy::STRICT],
            'unknown value → STRICT'      => ['GARBAGE',        StockPolicy::STRICT],
        ];
    }

    // ── canSell() — ALLOW_NEGATIVE policy ────────────────────────────────────

    public function testAllowNegativePolicyGrantsAnyUser(): void
    {
        $regular    = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $admin      = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);
        $superAdmin = new AuthUserDto(userId: 3, username: 'root', roles: ['ROLE_SUPER_ADMIN']);

        self::assertTrue($this->resolver->canSell(StockPolicy::ALLOW_NEGATIVE, $regular));
        self::assertTrue($this->resolver->canSell(StockPolicy::ALLOW_NEGATIVE, $admin));
        self::assertTrue($this->resolver->canSell(StockPolicy::ALLOW_NEGATIVE, $superAdmin));
    }

    // ── canSell() — STRICT policy ─────────────────────────────────────────────

    public function testStrictPolicyDeniesAnyUser(): void
    {
        $regular = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $admin   = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);

        self::assertFalse($this->resolver->canSell(StockPolicy::STRICT, $regular));
        self::assertFalse($this->resolver->canSell(StockPolicy::STRICT, $admin));
    }

    // ── canSell() — WARNING_ONLY policy ──────────────────────────────────────

    public function testWarningOnlyPolicyGrantsAdminsOnly(): void
    {
        $cashier    = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $admin      = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);
        $superAdmin = new AuthUserDto(userId: 3, username: 'superadmin', roles: ['ROLE_SUPER_ADMIN']);
        $noRoles    = new AuthUserDto(userId: 4, username: 'ghost', roles: []);

        self::assertFalse($this->resolver->canSell(StockPolicy::WARNING_ONLY, $cashier));
        self::assertTrue($this->resolver->canSell(StockPolicy::WARNING_ONLY, $admin));
        self::assertTrue($this->resolver->canSell(StockPolicy::WARNING_ONLY, $superAdmin));
        self::assertFalse($this->resolver->canSell(StockPolicy::WARNING_ONLY, $noRoles));
    }

    // ── shouldUseCoreValidation() ─────────────────────────────────────────────

    public function testV2AuthoritativeModeEnablesCoreValidation(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::INVENTORY_CORE_MODE, 'legacy', 'v2_authoritative'],
                [Constants::INVENTORY_CORE_DUAL_WRITE, 'false', 'false'],
            ]);

        self::assertTrue($this->resolver->shouldUseCoreValidation());
    }

    public function testLegacyModeWithDualWriteEnabledUsesCoreValidation(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::INVENTORY_CORE_MODE, 'legacy', 'legacy'],
                [Constants::INVENTORY_CORE_DUAL_WRITE, 'false', 'true'],
            ]);

        self::assertTrue($this->resolver->shouldUseCoreValidation());
    }

    public function testLegacyModeWithDualWriteDisabledSkipsCoreValidation(): void
    {
        $this->settings->method('get')
            ->willReturnMap([
                [Constants::INVENTORY_CORE_MODE, 'legacy', 'legacy'],
                [Constants::INVENTORY_CORE_DUAL_WRITE, 'false', 'false'],
            ]);

        self::assertFalse($this->resolver->shouldUseCoreValidation());
    }
}
