<?php

declare(strict_types=1);

namespace App\Tests\Sales\State;

use App\Catalog\Entity\Product;
use App\Identity\Dto\AuthUserDto;
use App\Inventory\Core\Enum\StockPolicy;
use App\Sales\State\NegativeStockPolicyResolver;
use App\Settings\Service\TenantSettingsService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class NegativeStockPolicyResolverTest extends TestCase
{
    private TenantSettingsService&MockObject $tenantSettings;
    private NegativeStockPolicyResolver $resolver;

    protected function setUp(): void
    {
        $this->tenantSettings = $this->createMock(TenantSettingsService::class);
        $this->resolver = new NegativeStockPolicyResolver($this->tenantSettings);
    }

    #[DataProvider('stockPolicyMappingProvider')]
    public function testResolvePolicyReturnsConfiguredEnum(StockPolicy $policy): void
    {
        $this->tenantSettings->method('getNegativeStockPolicy')->willReturn($policy);

        self::assertSame($policy, $this->resolver->resolvePolicy());
    }

    /** @return array<string, array{StockPolicy}> */
    public static function stockPolicyMappingProvider(): array
    {
        return [
            'STRICT'         => [StockPolicy::STRICT],
            'WARNING_ONLY'   => [StockPolicy::WARNING_ONLY],
            'ALLOW_NEGATIVE' => [StockPolicy::ALLOW_NEGATIVE],
        ];
    }

    public function testResolvePolicyDefaultsToStrictWhenServiceReturnsStrict(): void
    {
        $this->tenantSettings->method('getNegativeStockPolicy')->willReturn(StockPolicy::STRICT);

        self::assertSame(StockPolicy::STRICT, $this->resolver->resolvePolicy());
    }

    // ── canSell() ─────────────────────────────────────────────────────────────

    public function testAllowNegativePolicyGrantsAnyUser(): void
    {
        $user = new AuthUserDto(userId: 1, username: 'cashier', roles: ['ROLE_USER']);
        self::assertTrue($this->resolver->canSell(StockPolicy::ALLOW_NEGATIVE, $user));
    }

    public function testStrictPolicyDeniesAnyUser(): void
    {
        $admin = new AuthUserDto(userId: 2, username: 'admin', roles: ['ROLE_ADMIN']);
        self::assertFalse($this->resolver->canSell(StockPolicy::STRICT, $admin));
    }

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

    // ── shouldUseCoreValidation() — always true after migration ──────────────

    public function testShouldUseCoreValidationAlwaysReturnsTrue(): void
    {
        // All tenants are on v2_authoritative; the flag always returns true.
        self::assertTrue($this->resolver->shouldUseCoreValidation());
    }

    public function testStockTrackedOnlyForStockableProducts(): void
    {
        $product = new Product();
        $product->setType(1);

        self::assertTrue($this->resolver->isStockTracked($product));
    }

    public function testServiceProductDoesNotTrackStockEvenWhenLegacyFlagIsStale(): void
    {
        $product = new Product();
        $product->setType(2);
        $product->setTrackInventory(true);

        self::assertFalse($this->resolver->isStockTracked($product));
    }

    public function testDigitalProductDoesNotTrackStock(): void
    {
        $product = new Product();
        $product->setType(1);
        $product->setProductType('DIGITAL');
        $product->setTrackInventory(true);

        self::assertFalse($this->resolver->isStockTracked($product));
    }
}
