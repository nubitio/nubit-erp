<?php

namespace App\User\Domain;

interface UserRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(User $user): void;

    public function update(User $user): void;

    public function delete(string $id): void;

    public function getOneByUsername(string $username): ?User;
}
