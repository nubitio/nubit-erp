<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\JWTManagerInterface;
use App\Identity\Auth\TokenGeneratorService;
use App\Identity\Entity\RefreshToken;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;

final class TokenGeneratorServiceTest extends TestCase
{
    private function makeUser(): User
    {
        $role = new Role();
        $user = new User();
        $user->setRole($role);
        $user->setUsername('juan');

        return $user;
    }

    private function makeTenantContext(?int $tenantId = 1, ?string $tenantName = 'Demo'): TenantContext
    {
        $ctx = $this->createStub(TenantContext::class);
        $ctx->method('getTenantId')->willReturn($tenantId);
        $ctx->method('getTenantName')->willReturn($tenantName);

        return $ctx;
    }

    private function makeService(
        JWTManagerInterface $jwt,
        EntityManagerInterface $em,
        int $accessTtl = 3600,
        int $refreshTtl = 86400,
    ): TokenGeneratorService {
        return new TokenGeneratorService(
            $jwt,
            $em,
            $this->makeTenantContext(),
            $accessTtl,
            $refreshTtl,
        );
    }

    public function testGetAccessTokenTtl(): void
    {
        $jwt = $this->createStub(JWTManagerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService($jwt, $em, accessTtl: 1800);
        self::assertSame(1800, $service->getAccessTokenTtl());
    }

    public function testGetRefreshTokenTtl(): void
    {
        $jwt = $this->createStub(JWTManagerInterface::class);
        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService($jwt, $em, refreshTtl: 604800);
        self::assertSame(604800, $service->getRefreshTokenTtl());
    }

    public function testGenerateTokenPairCallsEncodeTwice(): void
    {
        $jwt = $this->createMock(JWTManagerInterface::class);
        $jwt->expects(self::exactly(2))
            ->method('encode')
            ->willReturnOnConsecutiveCalls('access-token', 'refresh-token');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')
            ->with(self::isInstanceOf(RefreshToken::class));
        $em->expects(self::once())->method('flush');

        $service = $this->makeService($jwt, $em);
        $dto = $service->generateTokenPair($this->makeUser());

        self::assertSame('access-token', $dto->accessToken);
        self::assertSame('refresh-token', $dto->refreshToken);
    }

    public function testGenerateTokenPairExpiryCalculatedFromTtl(): void
    {
        $jwt = $this->createStub(JWTManagerInterface::class);
        $jwt->method('encode')->willReturn('tok');

        $em = $this->createStub(EntityManagerInterface::class);

        $before = time();
        $service = $this->makeService($jwt, $em, accessTtl: 3600, refreshTtl: 86400);
        $dto = $service->generateTokenPair($this->makeUser());
        $after = time();

        self::assertGreaterThanOrEqual($before + 3600, $dto->accessTokenExpiresAt);
        self::assertLessThanOrEqual($after + 3600, $dto->accessTokenExpiresAt);
        self::assertGreaterThanOrEqual($before + 86400, $dto->refreshTokenExpiresAt);
    }

    public function testGenerateTokenPairSetsBranchIdFromBranch(): void
    {
        $jwt = $this->createStub(JWTManagerInterface::class);
        $jwt->method('encode')->willReturn('tok');
        $em = $this->createStub(EntityManagerInterface::class);

        $branch = $this->createStub(\App\Organization\Entity\Branch::class);
        $branch->method('getId')->willReturn('br-42');
        $branch->method('getName')->willReturn('Sucursal Lima');

        $service = $this->makeService($jwt, $em);
        $dto = $service->generateTokenPair($this->makeUser(), $branch);

        self::assertSame('br-42', $dto->branchId);
        self::assertSame('Sucursal Lima', $dto->branchName);
    }

    public function testGenerateTokenPairNullBranchIdWhenNoBranch(): void
    {
        $jwt = $this->createStub(JWTManagerInterface::class);
        $jwt->method('encode')->willReturn('tok');
        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService($jwt, $em);
        $dto = $service->generateTokenPair($this->makeUser(), null);

        self::assertNull($dto->branchId);
        self::assertNull($dto->branchName);
    }

    public function testPayloadIncludesTenantContext(): void
    {
        $capturedPayloads = [];
        $jwt = $this->createStub(JWTManagerInterface::class);
        $jwt->method('encode')->willReturnCallback(
            static function (array $payload) use (&$capturedPayloads): string {
                $capturedPayloads[] = $payload;
                return 'tok';
            }
        );
        $em = $this->createStub(EntityManagerInterface::class);

        $service = $this->makeService($jwt, $em);
        $service->generateTokenPair($this->makeUser());

        // First call = access token payload
        self::assertSame(1, $capturedPayloads[0]['tenantId']);
        self::assertSame('Demo', $capturedPayloads[0]['tenantName']);
        self::assertSame('juan', $capturedPayloads[0]['username']);
        self::assertSame('access', $capturedPayloads[0]['type']);
    }
}
