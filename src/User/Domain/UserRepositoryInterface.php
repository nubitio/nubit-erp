<?php

namespace App\User\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface UserRepositoryInterface
{
    /**
     * @return PaginatorInterface<User>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?User;

    public function insert(User $user): User;

    public function update(User $user): User;

    public function delete(int $id): void;

    public function getOneByUsername(string $username): ?User;

    public function changePassword(int $userId, string $newPassword): void;
}
