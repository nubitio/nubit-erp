<?php

declare(strict_types=1);

namespace App\Tests\Core\Context;

use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;

final class TenantContextTest extends TestCase
{
    public function testSetAndGetTenant(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-123');

        $this->assertSame(1, $ctx->getTenantId());
        $this->assertSame('acme', $ctx->getTenantName());
        $this->assertSame('acme.example.com', $ctx->getTenantDomain());
        $this->assertSame('req-123', $ctx->getRequestId());
    }

    public function testClearResetsAllFields(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-123');
        $ctx->setActor('cli:app:test', 'cli', 'app:test');
        $ctx->clear();

        $this->assertNull($ctx->getTenantId());
        $this->assertNull($ctx->getTenantName());
        $this->assertNull($ctx->getTenantDomain());
        $this->assertNull($ctx->getRequestId());
        $this->assertNull($ctx->getActorIdentifier());
        $this->assertNull($ctx->getChannel());
        $this->assertNull($ctx->getCommandName());
    }

    public function testDefaultsAreNull(): void
    {
        $ctx = new TenantContext();

        $this->assertNull($ctx->getTenantId());
        $this->assertNull($ctx->getTenantName());
        $this->assertNull($ctx->getTenantDomain());
        $this->assertNull($ctx->getRequestId());
        $this->assertNull($ctx->getActorIdentifier());
        $this->assertNull($ctx->getChannel());
        $this->assertNull($ctx->getCommandName());
    }

    public function testSetAndGetActor(): void
    {
        $ctx = new TenantContext();
        $ctx->setActor('cli:app:test', 'cli', 'app:test');

        $this->assertSame('cli:app:test', $ctx->getActorIdentifier());
        $this->assertSame('cli', $ctx->getChannel());
        $this->assertSame('app:test', $ctx->getCommandName());
    }

    public function testSetActorWithNullCommandName(): void
    {
        $ctx = new TenantContext();
        $ctx->setActor(null, 'http');

        $this->assertNull($ctx->getActorIdentifier());
        $this->assertSame('http', $ctx->getChannel());
        $this->assertNull($ctx->getCommandName());
    }

    public function testSetActorOverwritesPrevious(): void
    {
        $ctx = new TenantContext();
        $ctx->setActor('cli:first', 'cli', 'first');
        $ctx->setActor('scheduler:second', 'scheduler', 'second');

        $this->assertSame('scheduler:second', $ctx->getActorIdentifier());
        $this->assertSame('scheduler', $ctx->getChannel());
        $this->assertSame('second', $ctx->getCommandName());
    }
}
