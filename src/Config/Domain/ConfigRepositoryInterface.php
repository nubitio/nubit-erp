<?php

namespace App\Config\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface ConfigRepositoryInterface
{
    /**
     * @return PaginatorInterface<Config>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?Config;

    public function insert(Config $config): Config;

    public function update(Config $config): Config;

    public function delete(int $id): void;

    public function getOneByKey(string $key): ?Config;
}
