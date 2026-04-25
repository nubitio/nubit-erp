<?php

declare(strict_types=1);

namespace App\Tests\Platform\Cache;

use App\Platform\Cache\CacheManager;
use App\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class CacheManagerTest extends TestCase
{
    public function testCacheKeysAreScopedByTenantName(): void
    {
        $cache = new ArrayAdapter();
        $tenantA = new TenantContext();
        $tenantA->setTenant(1, 'tenant-a', null, null);
        $tenantB = new TenantContext();
        $tenantB->setTenant(2, 'tenant-b', null, null);

        (new CacheManager($cache, $tenantA))->set('shared-key', 'value-a');
        (new CacheManager($cache, $tenantB))->set('shared-key', 'value-b');

        self::assertSame('value-a', (new CacheManager($cache, $tenantA))->get('shared-key'));
        self::assertSame('value-b', (new CacheManager($cache, $tenantB))->get('shared-key'));
    }

    public function testCacheUsesUnscopedKeyWhenTenantIsMissing(): void
    {
        $cache = new ArrayAdapter();
        $manager = new CacheManager($cache, new TenantContext());

        $manager->set('global-key', 'global-value');

        self::assertTrue($manager->has('global-key'));
        self::assertSame('global-value', $manager->get('global-key'));
        self::assertTrue($cache->hasItem('global-key'));
    }

    public function testRememberReusesCachedTenantScopedValue(): void
    {
        $context = new TenantContext();
        $context->setTenant(1, 'acme', null, null);
        $manager = new CacheManager(new ArrayAdapter(), $context);
        $calls = 0;

        self::assertSame('computed', $manager->remember('expensive', function () use (&$calls): string {
            ++$calls;

            return 'computed';
        }));
        self::assertSame('computed', $manager->remember('expensive', function () use (&$calls): string {
            ++$calls;

            return 'other';
        }));
        self::assertSame(1, $calls);
    }
}
