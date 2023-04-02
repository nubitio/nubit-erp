<?php

namespace App\Config\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ConfigRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Config>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Config;

    public function insert(Config $config): Config;

    public function update(Config $config): Config;

    public function delete(string $id): void;

    public function getOneByKey(string $key): ?Config;
}