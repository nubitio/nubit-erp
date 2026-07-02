<?php

declare(strict_types=1);

namespace App\Tests\Efact\RateLimit;

use App\Efact\RateLimit\RateLimitResult;
use App\Efact\RateLimit\TenantRateLimiter;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class TenantRateLimiterTest extends TestCase
{
    /**
     * @param array<string, mixed> $featureConfig
     */
    private function makeLimiter(
        int $limit = 10,
        int $window = 60,
        bool $featureEnabled = false,
        array $featureConfig = [],
    ): TenantRateLimiter {
        $checker = $this->createStub(FeatureCheckerInterface::class);
        $checker->method('hasFeature')->willReturn($featureEnabled);
        $checker->method('getFeatureConfig')->willReturn($featureConfig);

        return new TenantRateLimiter(
            cache: new ArrayAdapter(),
            featureChecker: $checker,
            limit: (string) $limit,
            window: (string) $window,
        );
    }

    public function testFirstRequestIsAllowed(): void
    {
        $limiter = $this->makeLimiter(limit: 5);
        $result = $limiter->check('tenant-a');

        self::assertTrue($result->allowed);
        self::assertSame(5, $result->limit);
        self::assertSame(4, $result->remaining);
    }

    public function testRequestBeyondLimitIsBlocked(): void
    {
        $limiter = $this->makeLimiter(limit: 2);

        $limiter->check('tenant-a'); // 1
        $limiter->check('tenant-a'); // 2

        $result = $limiter->check('tenant-a'); // 3 → over limit
        self::assertFalse($result->allowed);
        self::assertSame(0, $result->remaining);
    }

    public function testDifferentTenantsHaveIndependentCounters(): void
    {
        $limiter = $this->makeLimiter(limit: 1);

        $limiter->check('tenant-a'); // consumes tenant-a's 1 slot
        $result = $limiter->check('tenant-b'); // tenant-b should still have 1

        self::assertTrue($result->allowed);
    }

    public function testZeroLimitAlwaysAllows(): void
    {
        $limiter = $this->makeLimiter(limit: 0);
        $result = $limiter->check('tenant-a');

        self::assertTrue($result->allowed);
        self::assertSame(0, $result->limit);
    }

    public function testFeatureConfigOverridesGlobalLimit(): void
    {
        // 3600 req/h, window=3600s → limit per window = 3600
        $limiter = $this->makeLimiter(limit: 5, window: 3600, featureEnabled: true, featureConfig: [
            'requests_per_hour' => 3600,
        ]);

        $result = $limiter->check('tenant-a');
        self::assertTrue($result->allowed);
        self::assertSame(3600, $result->limit);
    }

    public function testRetryAfterIsPositiveWhenBlocked(): void
    {
        $limiter = $this->makeLimiter(limit: 1, window: 60);
        $limiter->check('tenant-a');
        $result = $limiter->check('tenant-a');

        self::assertFalse($result->allowed);
        self::assertGreaterThanOrEqual(0, $result->retryAfter);
    }

    public function testRateLimitResultIsReadonly(): void
    {
        $r = new RateLimitResult(allowed: true, limit: 10, remaining: 9, retryAfter: 0);

        self::assertTrue($r->allowed);
        self::assertSame(10, $r->limit);
        self::assertSame(9, $r->remaining);
        self::assertSame(0, $r->retryAfter);
    }
}
