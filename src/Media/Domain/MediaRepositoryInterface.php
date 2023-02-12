<?php

namespace App\Media\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface MediaRepositoryInterface
{
    public function getOne(string $id): ?Media;

    public function insert(Media $media): Media;

    public function update(Media $media): Media;

    public function delete(string $id): void;
}