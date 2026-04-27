<?php

declare(strict_types=1);

namespace App\Tests\Platform\Tenant\Context;

use App\Core\Context\TenantContext as LegacyTenantContext;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function testPlatformContextTracksTenantAndActorLifecycle(): void
    {
        $context = new TenantContext();

        $context->setTenant(42, 'acme', 'acme.example.test', 'req-42');
        $context->setActor('user:99', 'http', 'invoice:create');

        self::assertSame(42, $context->getTenantId());
        self::assertSame('acme', $context->getTenantName());
        self::assertSame('acme.example.test', $context->getTenantDomain());
        self::assertSame('req-42', $context->getRequestId());
        self::assertSame('user:99', $context->getActorIdentifier());
        self::assertSame('http', $context->getChannel());
        self::assertSame('invoice:create', $context->getCommandName());

        $context->clear();

        self::assertNull($context->getTenantId());
        self::assertNull($context->getTenantName());
        self::assertNull($context->getTenantDomain());
        self::assertNull($context->getRequestId());
        self::assertNull($context->getActorIdentifier());
        self::assertNull($context->getChannel());
        self::assertNull($context->getCommandName());
    }

    public function testLegacyCoreContextRemainsCompatibleWithPlatformContext(): void
    {
        $context = new LegacyTenantContext();

        self::assertInstanceOf(TenantContext::class, $context);

        $context->setTenant(7, 'legacy', 'legacy.example.test', 'req-legacy');
        $context->setActor('cli:tenant:sync', 'cli', 'tenant:sync');

        self::assertSame(7, $context->getTenantId());
        self::assertSame('legacy', $context->getTenantName());
        self::assertSame('legacy.example.test', $context->getTenantDomain());
        self::assertSame('req-legacy', $context->getRequestId());
        self::assertSame('cli:tenant:sync', $context->getActorIdentifier());
        self::assertSame('cli', $context->getChannel());
        self::assertSame('tenant:sync', $context->getCommandName());
    }
}
