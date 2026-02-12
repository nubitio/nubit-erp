<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Context\TenantContext;
use App\Core\Util\Constants;
use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
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
        private readonly TenantContext $tenantContext,
    ) {
        parent::__construct($registry, Media::class);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function delete(string $id): void
    {
        $entity = $this->getEntityManager()->find(Media::class, $id);

        if (!$entity instanceof Media) {
            return;
        }

        try {
            $this->uploadsFilesystem->delete(
                Constants::IMAGE_UPLOAD_DIR . $this->tenantContext->getTenantName() . '/' . $entity->getPath()
            );
        } catch (FilesystemException $filesystemException) {
            $this->logger->alert($filesystemException->getMessage());
        }

        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
