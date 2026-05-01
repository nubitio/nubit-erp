<?php

declare(strict_types=1);

namespace App\Tests\ControlPlane\Billing;

use App\Billing\Entity\TaxDocument;
use App\Catalog\Entity\Product;
use App\Contacts\Entity\Customer;
use App\ControlPlane\Billing\DTO\EntitlementsResponse;
use App\ControlPlane\Billing\Entity\PlanFeature;
use App\ControlPlane\Billing\Entity\TenantFeatureOverride;
use App\ControlPlane\Billing\Feature\FeatureVoter;
use App\ControlPlane\Billing\Quota\EfactEntityQuotaResourceResolver;
use App\ControlPlane\Billing\Service\QuotaCheckResult;
use App\Identity\Entity\User;
use App\Organization\Entity\Branch;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class ControlPlaneBillingDomainTest extends TestCase
{
    // ── QuotaCheckResult ──────────────────────────────────────────────────────

    public function testQuotaCheckResultHoldsFields(): void
    {
        $r = new QuotaCheckResult(allowed: true, resource: 'products', current: 5, limit: 100);

        self::assertTrue($r->allowed);
        self::assertSame('products', $r->resource);
        self::assertSame(5, $r->current);
        self::assertSame(100, $r->limit);
    }

    public function testQuotaCheckResultDenied(): void
    {
        $r = new QuotaCheckResult(allowed: false, resource: 'users', current: 10, limit: 10);

        self::assertFalse($r->allowed);
        self::assertSame(10, $r->current);
        self::assertSame(10, $r->limit);
    }

    // ── PlanFeature ───────────────────────────────────────────────────────────

    /** @param array<string,mixed>|null $config */
    private function makePlanFeature(bool $enabled = true, ?array $config = null): PlanFeature
    {
        return new PlanFeature(
            id: 1,
            planName: 'pro',
            featureKey: 'excel_export',
            enabled: $enabled,
            configJson: $config,
            createdAt: new \DateTimeImmutable('2026-01-01'),
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testPlanFeatureIsEnabled(): void
    {
        self::assertTrue($this->makePlanFeature(true)->isEnabled());
        self::assertFalse($this->makePlanFeature(false)->isEnabled());
    }

    public function testPlanFeatureGetConfigReturnsValue(): void
    {
        $f = $this->makePlanFeature(config: ['max_rows' => 5000]);
        self::assertSame(5000, $f->getConfig('max_rows'));
    }

    public function testPlanFeatureGetConfigReturnsDefaultWhenMissing(): void
    {
        $f = $this->makePlanFeature(config: []);
        self::assertSame('default', $f->getConfig('missing_key', 'default'));
    }

    public function testPlanFeatureGetConfigReturnsNullWhenConfigIsNull(): void
    {
        $f = $this->makePlanFeature(config: null);
        self::assertNull($f->getConfig('any_key'));
    }

    public function testPlanFeatureGetConfigJson(): void
    {
        $config = ['max_rows' => 1000, 'formats' => ['xlsx', 'csv']];
        $f = $this->makePlanFeature(config: $config);
        self::assertSame($config, $f->getConfigJson());
    }

    // ── TenantFeatureOverride ─────────────────────────────────────────────────

    private function makeOverride(?\DateTimeImmutable $expiresAt = null, bool $enabled = true): TenantFeatureOverride
    {
        return new TenantFeatureOverride(
            id: 42,
            tenantId: 7,
            featureKey: 'excel_export',
            enabled: $enabled,
            configJson: null,
            expiresAt: $expiresAt,
            reason: 'Trial extension',
            cleanedAt: null,
            createdAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    public function testOverrideIsActiveWhenNoExpiry(): void
    {
        $override = $this->makeOverride(expiresAt: null);
        self::assertTrue($override->isActive());
        self::assertFalse($override->isExpired());
    }

    public function testOverrideIsActiveWhenExpiryIsFuture(): void
    {
        $override = $this->makeOverride(expiresAt: new \DateTimeImmutable('+1 year'));
        self::assertTrue($override->isActive());
        self::assertFalse($override->isExpired());
    }

    public function testOverrideIsExpiredWhenExpiryIsPast(): void
    {
        $override = $this->makeOverride(expiresAt: new \DateTimeImmutable('-1 day'));
        self::assertFalse($override->isActive());
        self::assertTrue($override->isExpired());
    }

    public function testOverrideGetConfig(): void
    {
        $override = new TenantFeatureOverride(
            id: 1,
            tenantId: 1,
            featureKey: 'k',
            enabled: true,
            configJson: ['limit' => 50],
            expiresAt: null,
            reason: null,
            cleanedAt: null,
            createdAt: new \DateTimeImmutable(),
        );
        self::assertSame(50, $override->getConfig('limit'));
        self::assertNull($override->getConfig('missing'));
    }

    // ── FeatureVoter ──────────────────────────────────────────────────────────

    public function testFeatureVoterGrantsWhenCheckerReturnsTrue(): void
    {
        $checker = $this->createMock(FeatureCheckerInterface::class);
        $checker->expects(self::once())->method('hasFeature')->with('excel_export')->willReturn(true);

        $voter = new FeatureVoter($checker);
        $token = $this->createStub(TokenInterface::class);

        // Use reflection to call protected voteOnAttribute
        $ref = new \ReflectionMethod($voter, 'voteOnAttribute');
        $result = $ref->invoke($voter, 'FEATURE_EXCEL_EXPORT', null, $token);

        self::assertTrue($result);
    }

    public function testFeatureVoterDeniesWhenCheckerReturnsFalse(): void
    {
        $checker = $this->createMock(FeatureCheckerInterface::class);
        $checker->expects(self::once())->method('hasFeature')->willReturn(false);

        $voter = new FeatureVoter($checker);
        $token = $this->createStub(TokenInterface::class);

        $ref = new \ReflectionMethod($voter, 'voteOnAttribute');
        $result = $ref->invoke($voter, 'FEATURE_EXCEL_EXPORT', null, $token);

        self::assertFalse($result);
    }

    public function testFeatureVoterSupportsOnlyFeaturePrefix(): void
    {
        $voter = new FeatureVoter($this->createStub(FeatureCheckerInterface::class));

        $ref = new \ReflectionMethod($voter, 'supports');
        self::assertTrue($ref->invoke($voter, 'FEATURE_ANY', null));
        self::assertFalse($ref->invoke($voter, 'ROLE_ADMIN', null));
        self::assertFalse($ref->invoke($voter, 'IS_AUTHENTICATED', null));
    }

    public function testFeatureVoterConvertsAttributeToLowercaseKey(): void
    {
        $checker = $this->createMock(FeatureCheckerInterface::class);
        $checker->expects(self::once())->method('hasFeature')->with('multi_branch')->willReturn(true);

        $voter = new FeatureVoter($checker);
        $ref = new \ReflectionMethod($voter, 'voteOnAttribute');
        $ref->invoke($voter, 'FEATURE_MULTI_BRANCH', null, $this->createStub(TokenInterface::class));
    }

    // ── EfactEntityQuotaResourceResolver ──────────────────────────────────────

    /** @return array<string, array{object, bool, string|null}> */
    public static function entityProvider(): array
    {
        return [
            'User'         => [new User(), true, 'users'],
            'Customer'     => [new Customer(), true, 'customers'],
            'Product'      => [new Product(), true, 'products'],
            'TaxDocument'  => [new TaxDocument(), true, 'invoices'],
            'Branch'       => [new Branch(), true, 'branches'],
            'stdClass'     => [new \stdClass(), false, null],
        ];
    }

    #[DataProvider('entityProvider')]
    public function testEfactEntityQuotaResourceResolver(object $entity, bool $supported, ?string $expectedResource): void
    {
        $resolver = new EfactEntityQuotaResourceResolver();

        self::assertSame($supported, $resolver->supports($entity));
        self::assertSame($expectedResource, $resolver->resolveResource($entity));
    }

    // ── EntitlementsResponse ──────────────────────────────────────────────────

    public function testEntitlementsResponseToArraySimpleMode(): void
    {
        $response = new EntitlementsResponse(
            plan: 'pro',
            limits: ['products' => ['limit' => 100, 'current' => 5]],
            features: [
                'excel_export' => ['enabled' => true, 'source' => 'plan', 'config' => []],
                'multi_branch' => ['enabled' => false, 'source' => 'plan', 'config' => []],
            ],
            debugMode: false,
        );

        $arr = $response->toArray();

        self::assertSame('pro', $arr['plan']);
        self::assertTrue($arr['features']['excel_export']);
        self::assertFalse($arr['features']['multi_branch']);
        // In simple mode, features are bool, not arrays
        self::assertIsBool($arr['features']['excel_export']);
    }

    public function testEntitlementsResponseToArrayDebugMode(): void
    {
        $response = new EntitlementsResponse(
            plan: 'starter',
            limits: [],
            features: [
                'excel_export' => ['enabled' => true, 'source' => 'override', 'config' => ['max_rows' => 500]],
            ],
            debugMode: true,
        );

        $arr = $response->toArray();

        self::assertIsArray($arr['features']['excel_export']);
        self::assertTrue($arr['features']['excel_export']['enabled']);
        self::assertSame('override', $arr['features']['excel_export']['source']);
        self::assertSame(['max_rows' => 500], $arr['features']['excel_export']['config']);
    }
}
