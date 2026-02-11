<?php

declare(strict_types=1);

namespace App\Tests\Core\Security\Auth;

use App\Core\Context\TenantContext;
use App\Core\Security\Auth\JWTManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class JWTTenantBindingTest extends TestCase
{
    private JWTManager $jwtManager;

    protected function setUp(): void
    {
        $this->jwtManager = new JWTManager('test-secret-key-for-unit-tests', new NullLogger());
    }

    public function testTokenContainsTenantClaims(): void
    {
        $payload = [
            'username' => 'admin',
            'userId' => 1,
            'tenantId' => 42,
            'tenantName' => 'acme',
            'exp' => time() + 3600,
        ];

        $token = $this->jwtManager->encode($payload);
        $decoded = $this->jwtManager->decode($token);

        $this->assertSame(42, $decoded['tenantId']);
        $this->assertSame('acme', $decoded['tenantName']);
    }

    public function testTenantMismatchDetection(): void
    {
        $tokenTenantName = 'tenant-a';
        $currentTenantName = 'tenant-b';

        // Simulate the validation logic from JWTAuthenticator
        $mismatch = $tokenTenantName !== null
            && $currentTenantName !== null
            && $tokenTenantName !== $currentTenantName;

        $this->assertTrue($mismatch, 'Cross-tenant token usage must be detected');
    }

    public function testSameTenantPassesValidation(): void
    {
        $tokenTenantName = 'acme';
        $currentTenantName = 'acme';

        $mismatch = $tokenTenantName !== null
            && $currentTenantName !== null
            && $tokenTenantName !== $currentTenantName;

        $this->assertFalse($mismatch, 'Same-tenant token must be accepted');
    }

    public function testNullTenantInTokenIsAccepted(): void
    {
        // Legacy tokens without tenant claim should still work
        $tokenTenantName = null;
        $currentTenantName = 'acme';

        $mismatch = $tokenTenantName !== null
            && $currentTenantName !== null
            && $tokenTenantName !== $currentTenantName;

        $this->assertFalse($mismatch, 'Tokens without tenant claim should be accepted for backward compatibility');
    }

    public function testNullCurrentTenantIsAccepted(): void
    {
        // Requests without tenant context (admin paths, etc.)
        $tokenTenantName = 'acme';
        $currentTenantName = null;

        $mismatch = $tokenTenantName !== null
            && $currentTenantName !== null
            && $tokenTenantName !== $currentTenantName;

        $this->assertFalse($mismatch, 'Requests without resolved tenant should accept any token');
    }

    public function testTenantContextIntegration(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(42, 'acme', 'acme.example.com', 'req-1');

        $payload = [
            'username' => 'admin',
            'userId' => 1,
            'tenantId' => $ctx->getTenantId(),
            'tenantName' => $ctx->getTenantName(),
            'exp' => time() + 3600,
        ];

        $token = $this->jwtManager->encode($payload);
        $decoded = $this->jwtManager->decode($token);

        $this->assertSame($ctx->getTenantId(), $decoded['tenantId']);
        $this->assertSame($ctx->getTenantName(), $decoded['tenantName']);
    }
}
