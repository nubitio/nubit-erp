<?php

declare(strict_types=1);

namespace App\Tests\Core\Context;

use App\Core\Context\TenantContext;
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
        $ctx->clear();

        $this->assertNull($ctx->getTenantId());
        $this->assertNull($ctx->getTenantName());
        $this->assertNull($ctx->getTenantDomain());
        $this->assertNull($ctx->getRequestId());
    }

    public function testDefaultsAreNull(): void
    {
        $ctx = new TenantContext();

        $this->assertNull($ctx->getTenantId());
        $this->assertNull($ctx->getTenantName());
        $this->assertNull($ctx->getTenantDomain());
        $this->assertNull($ctx->getRequestId());
    }
}
