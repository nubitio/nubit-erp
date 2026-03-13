<?php

declare(strict_types=1);

namespace App\Admin\User;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\Persistence\ConnectionRegistry;

final class AdminUserRepository
{
    private readonly Connection $connection;

    public function __construct(ConnectionRegistry $connectionRegistry)
    {
        /** @var Connection $conn */
        $conn = $connectionRegistry->getConnection('control_plane');
        $this->connection = $conn;
    }

    /**
     * @return array<int, array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string}>
     *
     * @throws Exception
     */
    public function findAll(): array
    {
        /** @var array<int, array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, username, roles, is_active, last_login_at, created_at
             FROM control_plane_admin_users
             ORDER BY created_at DESC',
        );

        return $rows;
    }

    /**
     * @return array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string}|null
     *
     * @throws Exception
     */
    public function findById(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, roles, is_active, last_login_at, created_at
             FROM control_plane_admin_users
             WHERE id = ?
             LIMIT 1',
            [$id],
        );

        if ($row === false) {
            return null;
        }

        /** @var array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string} $row */
        return $row;
    }

    /**
     * @return array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string}|null
     *
     * @throws Exception
     */
    public function findByUsername(string $username): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, username, roles, is_active, last_login_at, created_at
             FROM control_plane_admin_users
             WHERE username = ?
             LIMIT 1',
            [$username],
        );

        if ($row === false) {
            return null;
        }

        /** @var array{id: int, username: string, roles: string, is_active: bool, last_login_at: ?string, created_at: string} $row */
        return $row;
    }

    /**
     * Creates a new admin user and returns the new ID.
     *
     * @param array<int, string> $roles
     *
     * @throws Exception
     */
    public function create(string $username, string $passwordHash, array $roles): int
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->insert('control_plane_admin_users')
            ->values([
                'username'      => ':username',
                'password_hash' => ':password_hash',
                'roles'         => ':roles',
                'is_active'     => ':is_active',
                'created_at'    => 'NOW()',
                'updated_at'    => 'NOW()',
            ])
            ->setParameter('username', $username)
            ->setParameter('password_hash', $passwordHash)
            ->setParameter('roles', json_encode($roles, JSON_THROW_ON_ERROR))
            ->setParameter('is_active', 1);

        $qb->executeStatement();

        return (int) $this->connection->lastInsertId();
    }

    /**
     * Updates fields of an existing admin user.
     * Only `password_hash`, `roles`, and `is_active` are supported.
     *
     * @param array<string, mixed> $fields
     *
     * @throws Exception
     */
    public function update(int $id, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->update('control_plane_admin_users')
            ->where('id = :id')
            ->setParameter('id', $id);

        if (isset($fields['password_hash'])) {
            $qb->set('password_hash', ':password_hash')
                ->setParameter('password_hash', (string) $fields['password_hash']);
        }

        if (isset($fields['roles']) && is_array($fields['roles'])) {
            $qb->set('roles', ':roles')
                ->setParameter('roles', json_encode($fields['roles'], JSON_THROW_ON_ERROR));
        }

        if (array_key_exists('is_active', $fields)) {
            $qb->set('is_active', ':is_active')
                ->setParameter('is_active', (bool) $fields['is_active'] ? 1 : 0);
        }

        $qb->set('updated_at', 'NOW()');

        $qb->executeStatement();
    }

    /**
     * @throws Exception
     */
    public function deactivate(int $id): void
    {
        $this->update($id, ['is_active' => false]);
    }

    /**
     * @throws Exception
     */
    public function activate(int $id): void
    {
        $this->update($id, ['is_active' => true]);
    }

    /**
     * @throws Exception
     */
    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM control_plane_admin_users WHERE username = ?';
        $params = [$username];

        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return (int) $this->connection->fetchOne($sql, $params) > 0;
    }
}
