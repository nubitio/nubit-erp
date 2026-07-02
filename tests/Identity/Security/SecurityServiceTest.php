<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use App\Identity\Dto\AuthUserDto;
use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use App\Identity\Security\SecurityService;
use Nubit\AdminBundle\Auth\JWTAuthenticationToken;
use Nubit\AdminBundle\Auth\JWTManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security as SecurityComponent;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class SecurityServiceTest extends TestCase
{
    private function makeUser(): User
    {
        $role = new Role();
        $user = new User();
        $user->setRole($role);
        $user->setUsername('juan');

        return $user;
    }

    private function makeJwtToken(User $user, string $rawToken = 'raw.jwt.token'): JWTAuthenticationToken
    {
        return new JWTAuthenticationToken($user, 'main', ['ROLE_USER'], $rawToken);
    }

    /**
     * @param array<string, mixed> $jwtDecoded
     */
    private function makeService(
        ?User $userInSecurity,
        ?TokenInterface $tokenInSecurity,
        array $jwtDecoded = [],
    ): SecurityService {
        $security = $this->createStub(SecurityComponent::class);
        $security->method('getUser')->willReturn($userInSecurity);
        $security->method('getToken')->willReturn($tokenInSecurity);

        $jwt = $this->createStub(JWTManagerInterface::class);
        $jwt->method('decode')->willReturn($jwtDecoded);

        return new SecurityService($security, $jwt);
    }

    // ── connectedUser ─────────────────────────────────────────────────────────

    public function testConnectedUserReturnsAuthUserDtoWhenAuthenticated(): void
    {
        $user = $this->makeUser();
        $token = $this->makeJwtToken($user);

        $service = $this->makeService($user, $token, [
            'userId' => 7,
            'username' => 'juan',
            'role' => 1,
            'roles' => ['ROLE_USER'],
        ]);

        $result = $service->connectedUser();

        self::assertInstanceOf(AuthUserDto::class, $result);
        self::assertSame('juan', $result->username);
    }

    public function testConnectedUserReturnsNullWhenNoUser(): void
    {
        $service = $this->makeService(null, null);
        self::assertNull($service->connectedUser());
    }

    public function testConnectedUserReturnsNullWhenUserIsNotIdentityUser(): void
    {
        // getUser returns a non-User object (e.g. ControlPlaneUser)
        $service = $this->makeService(null, null);
        self::assertNull($service->connectedUser());
    }

    public function testConnectedUserReturnsNullWhenTokenIsNotJWTToken(): void
    {
        $user = $this->makeUser();
        $nonJwtToken = $this->createStub(TokenInterface::class);

        $service = $this->makeService($user, $nonJwtToken);
        self::assertNull($service->connectedUser());
    }

    // ── isAuthenticated ───────────────────────────────────────────────────────

    public function testIsAuthenticatedTrueWhenConnectedUserExists(): void
    {
        $user = $this->makeUser();
        $token = $this->makeJwtToken($user);

        $service = $this->makeService($user, $token, [
            'userId' => 7,
            'username' => 'juan',
            'role' => 1,
            'roles' => ['ROLE_USER'],
        ]);

        self::assertTrue($service->isAuthenticated());
    }

    public function testIsAuthenticatedFalseWhenNoUser(): void
    {
        $service = $this->makeService(null, null);
        self::assertFalse($service->isAuthenticated());
    }
}
