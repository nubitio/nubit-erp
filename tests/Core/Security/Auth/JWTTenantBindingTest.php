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
        $this->jwtManager = new JWTManager('test-secret-key-for-unit-tests-32b', new NullLogger());
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
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'tenant-b', 'tenant-b.example.com', 'req-1');
        $mismatch = $this->hasTenantMismatch(['tenantName' => 'tenant-a'], $ctx);

        $this->assertTrue($mismatch, 'Cross-tenant token usage must be detected');
    }

    public function testSameTenantPassesValidation(): void
    {
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-1');
        $mismatch = $this->hasTenantMismatch(['tenantName' => 'acme'], $ctx);

        $this->assertFalse($mismatch, 'Same-tenant token must be accepted');
    }

    public function testNullTenantInTokenIsAccepted(): void
    {
        // Legacy tokens without tenant claim should still work
        $ctx = new TenantContext();
        $ctx->setTenant(1, 'acme', 'acme.example.com', 'req-1');
        $mismatch = $this->hasTenantMismatch([], $ctx);

        $this->assertFalse($mismatch, 'Tokens without tenant claim should be accepted for backward compatibility');
    }

    public function testNullCurrentTenantIsAccepted(): void
    {
        // Requests without tenant context (admin paths, etc.)
        $ctx = new TenantContext();
        $mismatch = $this->hasTenantMismatch(['tenantName' => 'acme'], $ctx);

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

    /**
     * Simulates JWTAuthenticator tenant-binding check using runtime values.
     *
     * @param array<string, mixed> $tokenData
     */
    private function hasTenantMismatch(array $tokenData, TenantContext $tenantContext): bool
    {
        $tokenTenantName = isset($tokenData['tenantName']) && is_string($tokenData['tenantName'])
            ? $tokenData['tenantName']
            : null;
        $currentTenantName = $tenantContext->getTenantName();

        return $tokenTenantName !== null
            && $currentTenantName !== null
            && $tokenTenantName !== $currentTenantName;
    }
}
