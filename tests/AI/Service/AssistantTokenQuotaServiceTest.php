<?php

declare(strict_types=1);

namespace App\Tests\AI\Service;

use App\AI\Service\AssistantTokenQuotaService;
use Nubit\Platform\Cache\CacheManager;
use Nubit\Platform\Exception\ServiceException;
use Nubit\Platform\Feature\Contract\FeatureCheckerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Response;

#[AllowMockObjectsWithoutExpectations]
final class AssistantTokenQuotaServiceTest extends TestCase
{
    public function testUnlimitedWhenCapIsZero(): void
    {
        $features = $this->createStub(FeatureCheckerInterface::class);
        $features->method('getFeatureConfig')->willReturn(['monthlyTokenCap' => 0]);
        $cache = new ReflectionClass(CacheManager::class)->newInstanceWithoutConstructor();

        $service = new AssistantTokenQuotaService($features, $cache, new NullLogger());
        $service->assertWithinQuota();
        $this->addToAssertionCount(1);
    }

    public function testThrowsPaymentRequiredWhenCapExceeded(): void
    {
        $features = $this->createStub(FeatureCheckerInterface::class);
        $features->method('getFeatureConfig')->willReturn(['monthlyTokenCap' => 100]);

        $cache = $this->createMock(CacheManager::class);
        $cache->method('get')->willReturn(100);

        $service = new AssistantTokenQuotaService($features, $cache, new NullLogger());

        $this->expectException(ServiceException::class);
        $this->expectExceptionCode(Response::HTTP_PAYMENT_REQUIRED);
        $service->assertWithinQuota();
    }

    public function testUnlimitedWhenConfigHasNoCap(): void
    {
        $features = $this->createStub(FeatureCheckerInterface::class);
        $features->method('getFeatureConfig')->willReturn([]);
        $cache = new ReflectionClass(CacheManager::class)->newInstanceWithoutConstructor();

        $service = new AssistantTokenQuotaService($features, $cache, new NullLogger());
        $service->assertWithinQuota();
        $this->addToAssertionCount(1);
    }
}
