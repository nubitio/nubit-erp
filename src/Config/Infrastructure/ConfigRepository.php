<?php

namespace App\Config\Infrastructure;

use App\Config\Domain\ConfigRepositoryInterface;
use App\Config\Domain\Config;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\Common\DataGridLoader;
use App\Core\Infrastructure\DataLoader\DataLoader;
use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\EasyStore;
use App\Core\Infrastructure\DataLoader\Options;
use Doctrine\ORM\EntityManagerInterface;

readonly class ConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    )
    {
    }

    public function getAll(Options $options): DataStoreInterface
    {
        $paginator = $this->dataLoader->load($options);

        $data = [];

        foreach ($paginator->getIterator() as $config) {
            $data[] = DataMapper::map($config, Config::class);
        }

        return new EasyStore($paginator->getTotalItems(), $data);
    }

    public function getOne(string $id): ?Config
    {
        /** @var ?ConfigEntity $entity */
        $entity = $this->entityManager->find(ConfigEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Config::class);
    }

    public function insert(Config $config): Config
    {
        $entity = DataMapper::map($config, ConfigEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Config::class);
    }

    public function update(Config $config): Config
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $config->id);
        $entity = DataMapper::map($config, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Config::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $id);
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}