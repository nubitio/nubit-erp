<?php

declare(strict_types=1);

namespace App\Tests\Identity\Auth;

use App\Identity\Auth\TokenPairDto;
use App\Identity\Dto\AuthResponseDto;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use PHPUnit\Framework\TestCase;

final class TokenPairDtoTest extends TestCase
{
    private function makeUser(string $username = 'juan'): User
    {
        $role = new Role();
        $user = new User();
        $user->setRole($role);
        $user->setUsername($username);
        // Set id so AuthUserDto::__construct(int $userId) receives non-null
        $idRef = new \ReflectionProperty(User::class, 'id');
        $idRef->setValue($user, 1);

        return $user;
    }

    private function makeDto(?string $branchId = null, ?string $branchName = null): TokenPairDto
    {
        return new TokenPairDto(
            accessToken: 'access-tok-abc',
            refreshToken: 'refresh-tok-xyz',
            accessTokenExpiresAt: 1_800_000_000,
            refreshTokenExpiresAt: 1_900_000_000,
            user: $this->makeUser(),
            branchId: $branchId,
            branchName: $branchName,
        );
    }

    // ── Constructor fields ────────────────────────────────────────────────────

    public function testConstructorExposesFields(): void
    {
        $dto = $this->makeDto('branch-1', 'Sucursal Lima');

        self::assertSame('access-tok-abc', $dto->accessToken);
        self::assertSame('refresh-tok-xyz', $dto->refreshToken);
        self::assertSame(1_800_000_000, $dto->accessTokenExpiresAt);
        self::assertSame(1_900_000_000, $dto->refreshTokenExpiresAt);
        self::assertSame('branch-1', $dto->branchId);
        self::assertSame('Sucursal Lima', $dto->branchName);
    }

    // ── toArray ───────────────────────────────────────────────────────────────

    public function testToArrayIncludesUserBlock(): void
    {
        $dto = $this->makeDto();
        $arr = $dto->toArray();

        self::assertArrayHasKey('user', $arr);
        self::assertSame('juan', $arr['user']['username']);
    }

    public function testToArrayIncludesTokensWhenEnabled(): void
    {
        $dto = $this->makeDto();
        $arr = $dto->toArray(includeTokens: true);

        self::assertArrayHasKey('token', $arr);
        self::assertArrayHasKey('refreshToken', $arr);
        self::assertArrayHasKey('expiresAt', $arr);
        self::assertSame('access-tok-abc', $arr['token']);
        self::assertSame('refresh-tok-xyz', $arr['refreshToken']);
        self::assertSame(1_800_000_000, $arr['expiresAt']);
    }

    public function testToArrayExcludesTokensWhenDisabled(): void
    {
        $dto = $this->makeDto();
        $arr = $dto->toArray(includeTokens: false);

        self::assertArrayNotHasKey('token', $arr);
        self::assertArrayNotHasKey('refreshToken', $arr);
        self::assertArrayNotHasKey('expiresAt', $arr);
    }

    public function testToArrayIncludesBranchWhenSet(): void
    {
        $dto = $this->makeDto('branch-42', 'Sucursal Norte');
        $arr = $dto->toArray();

        self::assertArrayHasKey('branch', $arr);
        self::assertSame('branch-42', $arr['branch']['id']);
        self::assertSame('Sucursal Norte', $arr['branch']['name']);
    }

    public function testToArrayExcludesBranchWhenNull(): void
    {
        $dto = $this->makeDto(branchId: null);
        $arr = $dto->toArray();

        self::assertArrayNotHasKey('branch', $arr);
    }

    // ── toAuthResponseDto ─────────────────────────────────────────────────────

    public function testToAuthResponseDtoReturnsAuthResponseDto(): void
    {
        $dto = $this->makeDto('br-1', 'Branch');
        $response = $dto->toAuthResponseDto();

        self::assertInstanceOf(AuthResponseDto::class, $response);
        self::assertSame('juan', $response->user->username);
    }

    public function testToAuthResponseDtoWithTokensIncluded(): void
    {
        $dto = $this->makeDto();
        $response = $dto->toAuthResponseDto(includeTokens: true);

        self::assertSame('access-tok-abc', $response->token);
        self::assertSame('refresh-tok-xyz', $response->refreshToken);
        self::assertSame(1_800_000_000, $response->expiresAt);
    }

    public function testToAuthResponseDtoWithTokensExcluded(): void
    {
        $dto = $this->makeDto();
        $response = $dto->toAuthResponseDto(includeTokens: false);

        self::assertNull($response->token);
        self::assertNull($response->refreshToken);
        self::assertNull($response->expiresAt);
    }

    public function testToAuthResponseDtoIncludesBranchWhenSet(): void
    {
        $dto = $this->makeDto('br-1', 'Sucursal');
        $response = $dto->toAuthResponseDto();

        self::assertNotNull($response->branch);
        self::assertSame('br-1', $response->branch->id);
    }

    public function testToAuthResponseDtoNullBranchWhenNone(): void
    {
        $dto = $this->makeDto();
        $response = $dto->toAuthResponseDto();

        self::assertNull($response->branch);
    }
}
