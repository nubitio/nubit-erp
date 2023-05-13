<?php

namespace App\Media\Domain;

interface MediaRepositoryInterface
{
    public function getOne(string $id): ?Media;

    public function insert(Media $media): Media;

    public function update(Media $media): Media;

    public function delete(string $id): void;
}
