<?php

namespace App\Media\Infrastructure;

use App\Core\Domain\Exception\ServiceException;
use App\Core\Domain\Util\Constants;
use App\Core\Domain\Util\DataMapper;
use App\Media\Domain\Media;
use App\Media\Domain\MediaRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

readonly class MediaRepository implements MediaRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FilesystemOperator     $uploadsFilesystem,
        private LoggerInterface        $logger
    )
    {
    }

    public function getOne(string $id): ?Media
    {
        /** @var ?MediaEntity $entity */
        $entity = $this->entityManager->find(MediaEntity::class, $id);

        if (!$entity) {
            return null;
        }

        return DataMapper::map($entity, Media::class);
    }

    public function insert(Media $media): Media
    {
        $entity = DataMapper::map($media, MediaEntity::class);
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Media::class);
    }

    /**
     * @throws ServiceException
     */
    public function update(Media $media): Media
    {
        $entity = $this->entityManager->find(MediaEntity::class, $media->id);

        if (!$entity) {
            throw new ServiceException('El archivo no existe.');
        }

        $entity = DataMapper::map($media, $entity);
        $this->entityManager->flush();

        return DataMapper::map($entity, Media::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->entityManager->find(MediaEntity::class, $id);

        if (!$entity) {
            return;
        }

        try {
            $this->uploadsFilesystem->delete(Constants::IMAGE_UPLOAD_DIR . $entity->path);
        } catch (FilesystemException $e) {
            $this->logger->alert($e->getMessage());
        }

        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }
}