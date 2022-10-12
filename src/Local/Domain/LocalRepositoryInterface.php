<?php

namespace App\Local\Domain;

interface LocalRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Local $local): void;

    public function update(Local $local): void;

    public function delete(string $id): void;
}