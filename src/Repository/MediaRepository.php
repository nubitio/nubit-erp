<?php

namespace App\Repository;

use App\Core\Util\Constants;
use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly FilesystemOperator $uploadsFilesystem,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($registry, Media::class);
    }

    public function delete(string $id): void
    {
        $entity = $this->_em->find(Media::class, $id);

        if (!$entity) {
            return;
        }

        try {
            $this->uploadsFilesystem->delete(Constants::IMAGE_UPLOAD_DIR . $entity->getPath());
        } catch (FilesystemException $filesystemException) {
            $this->logger->alert($filesystemException->getMessage());
        }

        $this->_em->remove($entity);
        $this->_em->flush();
    }
}
