<?php

namespace App\User\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface UserRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?User;

    public function insert(User $user): User;

    public function update(User $user): User;

    public function delete(string $id): void;

    public function getOneByUsername(string $username): ?User;
}