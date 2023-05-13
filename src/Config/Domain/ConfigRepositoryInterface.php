<?php

namespace App\Config\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface ConfigRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Config>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?Config;

    public function insert(Config $config): Config;

    public function update(Config $config): Config;

    public function delete(int $id): void;

    public function getOneByKey(string $key): ?Config;
}