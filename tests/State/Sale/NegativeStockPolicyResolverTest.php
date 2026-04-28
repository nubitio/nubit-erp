<?php

declare(strict_types=1);

namespace App\Tests\State\Sale;

use App\Identity\Dto\AuthUserDto;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Sales\State\NegativeStockPolicyResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class NegativeStockPolicyResolverTest extends TestCase
{
    private SettingsService&MockObject $settings;
    private NegativeStockPolicyResolver $resolver;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(SettingsService::class);
        $this->resolver = new NegativeStockPolicyResolver($this->settings);
    }

    // ── resolvePolicy() ───────────────────────────────────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('stockPolicyMappingProvider')]
    public function testResolvePolicyMapsRawValuesToConstants(string $raw, string $expected): void
    {
        $this->settings->method('get')
            ->with(Constants::INVENTORY_NEGATIVE_STOCK_POLICY, '')
            ->willReturn($raw);

        self::assertSame($expected, $this->resolver->resolvePolicy());
    }

    /** @return array<string, array{string, string}> */
    public static function stockPolicyMappingProvider(): array
    {
        return [
            'STRICT maps to BLOCK'                  => ['STRICT',          NegativeStockPolicyResolver::BLOCK],
            'WARNING_ONLY maps to ALLOW_WITH_ROLE'  => ['WARNING_ONLY',    NegativeStockPolicyResolver::ALLOW_WITH_ROLE],
            'ALLOW_NEGATIVE maps to ALLOW_ALL'      => ['ALLOW_NEGATIVE',  NegativeStockPolicyResolver::ALLOW_ALL],
            // Direct legacy string passthrough
            'BLOCK passthrough'                     => ['BLOCK',           NegativeStockPolicyResolver::BLOCK],
            'ALLOW_WITH_ROLE passthrough'           => ['ALLOW_WITH_ROLE', NegativeStockPolicyResolver::ALLOW_WITH_ROLE],
            'ALLOW_ALL passthrough'                 => ['ALLOW_ALL',       NegativeStockPolicyResolver::ALLOW_ALL],
            // Normalisation: lowercase input
            'strict lowercase maps to BLOCK'        => ['strict',          NegativeStockPolicyResolver::BLOCK],
            'allow_negative lowercase maps to ALLOW_ALL' => ['allow_negative', NegativeStockPolicyResolver::ALLOW_ALL],
            // Unknown / empty → safe default
            'empty string defaults to BLOCK'        => ['',                NegativeStockPolicyResolver::BLOCK],
            'unknown value defaults to BLOCK'       => ['GARBAGE',         NegativeStockPolicyResolver::BLOCK],
        ];
    }

    // ── canSell() — ALLOW_ALL policy ─────────────────────────────────────────

    public function testAllowAllPolicyGrantsAnyUser(): void
    {
        $regularUser  = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $adminUser    = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);
        $superAdmin   = new AuthUserDto(userId: 3, username: 'root', roles: ['ROLE_SUPER_ADMIN']);

        self::assertTrue($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_ALL, $regularUser));
        self::assertTrue($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_ALL, $adminUser));
        self::assertTrue($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_ALL, $superAdmin));
    }

    // ── canSell() — BLOCK policy ─────────────────────────────────────────────

    public function testBlockPolicyDeniesAnyUser(): void
    {
        $regularUser = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $adminUser   = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);

        self::assertFalse($this->resolver->canSell(NegativeStockPolicyResolver::BLOCK, $regularUser));
        self::assertFalse($this->resolver->canSell(NegativeStockPolicyResolver::BLOCK, $adminUser));
    }

    // ── canSell() — ALLOW_WITH_ROLE policy ───────────────────────────────────

    public function testAllowWithRoleGrantsAdminsOnly(): void
    {
        $cashier    = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        $admin      = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);
        $superAdmin = new AuthUserDto(userId: 3, username: 'superadmin', roles: ['ROLE_SUPER_ADMIN']);
        $noRoles    = new AuthUserDto(userId: 4, username: 'ghost', roles: []);

        self::assertFalse($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_WITH_ROLE, $cashier));
        self::assertTrue($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_WITH_ROLE, $admin));
        self::assertTrue($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_WITH_ROLE, $superAdmin));
        self::assertFalse($this->resolver->canSell(NegativeStockPolicyResolver::ALLOW_WITH_ROLE, $noRoles));
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
