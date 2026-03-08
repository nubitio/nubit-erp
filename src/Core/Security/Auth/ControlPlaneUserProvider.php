<?php

declare(strict_types=1);

namespace App\Core\Security\Auth;

use Override;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<ControlPlaneUser>
 */
final readonly class ControlPlaneUserProvider implements UserProviderInterface
{
    public function __construct(
        private ConnectionRegistry $connectionRegistry,
    ) {
    }

    /**
     * @throws Exception
     */
    #[Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        try {
            $user = $this->getControlPlaneConnection()->fetchAssociative(
                <<<'SQL'
                    SELECT id, username, password_hash, roles, is_active
                    FROM control_plane_admin_users
                    WHERE username = ?
                    LIMIT 1
                SQL,
                [$identifier],
            );
        } catch (Exception) {
            throw new UserNotFoundException('Control plane users are not available. Run control-plane migrations.');
        }
        if ($user === false) {
            throw new UserNotFoundException(sprintf('User "%s" does not exist.', $identifier));
        }

        if (!(bool) ($user['is_active'] ?? false)) {
            throw new UserNotFoundException(sprintf('User "%s" is inactive.', $identifier));
        }

        $roles = json_decode((string) ($user['roles'] ?? '[]'), true);
        if (!is_array($roles)) {
            $roles = ['ROLE_ADMIN'];
        }
        $roles = array_values(array_filter($roles, static fn (mixed $role): bool => is_string($role) && $role !== ''));
        if ($roles === []) {
            $roles = ['ROLE_ADMIN'];
        }

        return new ControlPlaneUser(
            id: (int) ($user['id'] ?? 0),
            username: (string) $user['username'],
            password: (string) $user['password_hash'],
            roles: $roles,
        );
    }

    #[Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ControlPlaneUser) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    #[Override]
    public function supportsClass(string $class): bool
    {
        return ControlPlaneUser::class === $class || is_subclass_of($class, ControlPlaneUser::class);
    }

    private function getControlPlaneConnection(): Connection
    {
        return $this->connectionRegistry->getConnection('control_plane');
    }
}
