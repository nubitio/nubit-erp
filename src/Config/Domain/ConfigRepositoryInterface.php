<?php

namespace App\Config\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ConfigRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?Config;

    public function insert(Config $config): Config;

    public function update(Config $config): Config;

    public function delete(string $id): void;

    public function getOneByKey(string $key): ?Config;
}