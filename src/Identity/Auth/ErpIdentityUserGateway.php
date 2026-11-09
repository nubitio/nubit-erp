<?php

declare(strict_types=1);

namespace App\Identity\Auth;

use App\Identity\Entity\Role;
use App\Identity\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\AdminBundle\Identity\Exception\IdentityException;
use Nubit\AdminBundle\Identity\IdentityUserGatewayInterface;
use Nubit\AdminBundle\Identity\PasswordPolicy;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bridges nubit/admin-bundle's identity module to this ERP's user model.
 *
 * The bundle's default gateway assumes the e-mail is the identifier and roles are plain strings.
 * Here the login identifier is `username`, the e-mail is optional contact data, and a user has ONE
 * {@see Role} entity. So:
 *  - lookups accept the e-mail (invitations, "forgot password") or the username (TOTP, reset tokens
 *    store the username as their subject);
 *  - roles travel on invitation tokens as `role:<id>` (any role, including custom ones), with the
 *    three legacy Symfony roles accepted too.
 */
final readonly class ErpIdentityUserGateway implements IdentityUserGatewayInterface
{
    private const array LEGACY_ROLE_IDS = ['ROLE_SUPER_ADMIN' => 1, 'ROLE_ADMIN' => 2, 'ROLE_CASHIER' => 3];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    #[Override]
    public function findByIdentifier(string $identifier): ?UserInterface
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        $repository = $this->entityManager->getRepository(User::class);
        foreach (['email', 'username'] as $field) {
            $user = $repository->findOneBy([$field => $identifier, 'deletedAt' => null]);
            if ($user instanceof User) {
                return $user;
            }
        }

        return null;
    }

    #[Override]
    public function changePassword(UserInterface $user, #[\SensitiveParameter] string $plainPassword): void
    {
        if (!$user instanceof User) {
            throw new IdentityException('This user cannot hold a password.');
        }
        PasswordPolicy::assertAcceptable($plainPassword);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->flush();
    }

    #[Override]
    public function createUser(
        string $identifier,
        #[\SensitiveParameter]
        string $plainPassword,
        array $roles,
    ): UserInterface {
        PasswordPolicy::assertAcceptable($plainPassword);
        $role = $this->resolveRole($roles);

        $user = new User()
            ->setUsername($this->uniqueUsername($identifier))
            ->setEmail($identifier)
            ->setRole($role);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /** @param array<array-key, mixed> $roles */
    private function resolveRole(array $roles): Role
    {
        foreach ($roles as $entry) {
            $entry = (string) $entry;
            $id = str_starts_with($entry, 'role:') ? (int) substr($entry, 5) : self::LEGACY_ROLE_IDS[$entry] ?? 0;
            $role = $id > 0 ? $this->entityManager->find(Role::class, $id) : null;
            if ($role instanceof Role) {
                return $role;
            }
        }

        throw new IdentityException('The invitation does not carry a valid role. Ask for a new one.');
    }

    /** "ana.perez+tienda@x.pe" -> "ana.perez" (then ana.perez2, ana.perez3...). */
    private function uniqueUsername(string $email): string
    {
        $local = strtolower((string) strstr($email, '@', true));
        $base = trim((string) preg_replace('/[^a-z0-9._-]+/', '', $local), '._-');
        $base = $base === '' ? 'usuario' : substr($base, 0, 40);

        $candidate = $base;
        for ($suffix = 2; $this->usernameTaken($candidate); ++$suffix) {
            $candidate = $base . $suffix;
        }

        return $candidate;
    }

    private function usernameTaken(string $username): bool
    {
        return $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username, 'deletedAt' => null]) !== null;
    }
}
