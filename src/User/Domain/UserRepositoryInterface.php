<?php

namespace App\User\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface UserRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<User>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?User;

    public function insert(User $user): User;

    public function update(User $user): User;

    public function delete(string $id): void;

    public function getOneByUsername(string $username): ?User;
}