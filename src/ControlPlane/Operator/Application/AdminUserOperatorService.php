<?php

declare(strict_types=1);

namespace App\ControlPlane\Operator\Application;

use App\ControlPlane\Admin\Security\ControlPlaneUser;
use App\ControlPlane\Admin\User\AdminUserRepository;
use App\Efact\Audit\AuditLogger;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Admin user mutations for the operator API.
 */
final readonly class AdminUserOperatorService
{
    public const array ALLOWED_ROLES = ['ROLE_CP_VIEWER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'];

    public function __construct(
        private AdminUserRepository $adminUserRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private AuditLogger $auditLogger,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     *
     * @throws Exception
     */
    public function listUsers(): array
    {
        return array_values(array_map($this->decodeUserRoles(...), $this->adminUserRepository->findAll()));
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws Exception
     */
    public function getUser(int $id): ?array
    {
        $user = $this->adminUserRepository->findById($id);

        return $user === null ? null : $this->decodeUserRoles($user);
    }

    /**
     * @param list<string> $roles
     * @return array{id: int}
     *
     * @throws Exception
     */
    public function createUser(
        string $username,
        string $password,
        string $confirmPassword,
        array $roles,
        string $actor,
    ): array {
        $username = strtolower(trim($username));
        $errors = $this->validateUsername($username);
        $errors = array_merge($errors, $this->validatePassword($password));

        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        $roles = $this->filterRoles($roles);
        if ($roles === []) {
            $errors[] = 'At least one role must be selected.';
        }

        if ($errors === [] && $this->adminUserRepository->usernameExists($username)) {
            $errors[] = sprintf('Username "%s" is already taken.', $username);
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->adminUserRepository->create($username, $this->hashPassword($password), $roles);

        $this->auditLogger->log('admin_user.create', [
            'actor' => $actor,
            'new_user' => $username,
            'user_id' => $newId,
            'roles' => $roles,
        ]);
        $this->logger->info('Admin user created', [
            'actor' => $actor,
            'new_user' => $username,
            'user_id' => $newId,
        ]);

        return ['id' => $newId];
    }

    /**
     * @param list<string> $roles
     *
     * @throws Exception
     */
    public function updateUser(int $id, array $roles, bool $isActive, string $actor, string $actorUsername): void
    {
        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            throw new InvalidArgumentException('Admin user not found.');
        }

        $roles = $this->filterRoles($roles);
        if ($roles === []) {
            throw new InvalidArgumentException('At least one role must be selected.');
        }

        if ($actorUsername === (string) $user['username'] && !$isActive) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }

        $this->adminUserRepository->update($id, [
            'roles' => $roles,
            'is_active' => $isActive,
        ]);

        $this->auditLogger->log('admin_user.edit', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
            'roles' => $roles,
            'is_active' => $isActive,
        ]);
        $this->logger->info('Admin user edited', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
        ]);
    }

    /**
     * @return array{is_active: bool}
     *
     * @throws Exception
     */
    public function toggleUser(int $id, string $actor, string $actorUsername): array
    {
        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            throw new InvalidArgumentException('Admin user not found.');
        }

        if ($actorUsername === (string) $user['username']) {
            throw new InvalidArgumentException('You cannot deactivate your own account.');
        }

        $wasActive = (bool) $user['is_active'];
        if ($wasActive) {
            $this->adminUserRepository->deactivate($id);
        } else {
            $this->adminUserRepository->activate($id);
        }

        $newStatus = !$wasActive;

        $this->auditLogger->log('admin_user.toggle', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
            'is_active' => $newStatus,
        ]);
        $this->logger->info('Admin user toggled', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
            'is_active' => $newStatus,
        ]);

        return ['is_active' => $newStatus];
    }

    /**
     * @throws Exception
     */
    public function resetPassword(int $id, string $password, string $confirmPassword, string $actor): void
    {
        $user = $this->adminUserRepository->findById($id);
        if ($user === null) {
            throw new InvalidArgumentException('Admin user not found.');
        }

        $errors = $this->validatePassword($password);
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        if ($errors !== []) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->adminUserRepository->update($id, ['password_hash' => $this->hashPassword($password)]);

        $this->auditLogger->log('admin_user.reset_password', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
        ]);
        $this->logger->info('Admin user password reset', [
            'actor' => $actor,
            'user_id' => $id,
            'username' => (string) $user['username'],
        ]);
    }

    /**
     * @return list<string>
     */
    private function validateUsername(string $username): array
    {
        $errors = [];
        if ($username === '') {
            $errors[] = 'Username is required.';
        } elseif (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
            $errors[] = 'Username must be 3–64 characters and only use lowercase letters, digits, dots, hyphens, or underscores.';
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validatePassword(string $password): array
    {
        $errors = [];
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (mb_strlen($password) < 12) {
            $errors[] = 'Password must be at least 12 characters.';
        }

        return $errors;
    }

    /**
     * @param array<mixed> $raw
     *
     * @return list<string>
     */
    private function filterRoles(array $raw): array
    {
        $filtered = [];
        foreach ($raw as $role) {
            if (is_string($role) && in_array($role, self::ALLOWED_ROLES, true)) {
                $filtered[] = $role;
            }
        }

        return array_values(array_unique($filtered));
    }

    private function hashPassword(string $plainPassword): string
    {
        $tempUser = new ControlPlaneUser(id: 0, username: '_temp', password: '', roles: []);

        return $this->passwordHasher->hashPassword($tempUser, $plainPassword);
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function decodeUserRoles(array $user): array
    {
        $rolesRaw = $user['roles'] ?? '[]';
        $decoded = json_decode(is_string($rolesRaw) ? $rolesRaw : '[]', true);
        $user['roles'] = is_array($decoded) ? $decoded : [];
        $user['is_active'] = (bool) ($user['is_active'] ?? false);

        return $user;
    }
}
