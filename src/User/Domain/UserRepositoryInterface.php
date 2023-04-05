<?php

namespace App\User\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface UserRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<User>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?User;

    public function insert(User $user): User;

    public function update(User $user): User;

    public function delete(int $id): void;

    public function getOneByUsername(string $username): ?User;
}