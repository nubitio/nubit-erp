<?php

namespace App\Config\Infrastructure;

use App\Config\Domain\Config;
use App\Config\Domain\ConfigRepositoryInterface;
use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\DataMapper;
use App\Core\Infrastructure\DataLoader\DataLoader;
use Doctrine\ORM\EntityManagerInterface;

readonly class ConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DataLoader $dataLoader,
    ) {
    }

    public function getAll(LoadOptions $options): PaginatorInterface
    {
        return $this->dataLoader->loadCollection($options);
    }

    public function getOne(int $id): ?Config
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

    /**
     * @throws ServiceException
     */
    public function update(Config $config): Config
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $config->id);

        if (!$entity) {
            throw new ServiceException('La configuración no existe.');
        }

        $entity = DataMapper::map($config, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Config::class);
    }

    public function delete(int $id): void
    {
        $entity = $this->entityManager->find(ConfigEntity::class, $id);

        if (!$entity) {
            return;
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    public function getOneByKey(string $key): ?Config
    {
        /** @var ?ConfigEntity $entity */
        $entity = $this->entityManager->getRepository(ConfigEntity::class)->findOneBy(['name' => $key]);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Config::class);
    }
}
