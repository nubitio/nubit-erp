<?php

declare(strict_types=1);

namespace App\Efact\Service;

use App\Efact\Config\EfactConstants;
use App\Efact\Filesystem\S3UrlResolver;
use App\Organization\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;

readonly class LogoProvider
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private S3UrlResolver $s3UrlResolver,
        private TenantContext $tenantContext,
    ) {}

    public function __invoke(): string
    {
        $tenantName = $this->tenantContext->getTenantName();
        if ($tenantName === null) {
            return '';
        }

        try {
            $company = $this->entityManager
                ->createQuery(<<<'DQL'
                    SELECT c, logo
                    FROM App\Organization\Entity\Company c
                    LEFT JOIN FETCH c.logo logo
                    DQL)
                ->setMaxResults(1)
                ->getOneOrNullResult();
        } catch (\Throwable) {
            return '';
        }

        if (!$company instanceof Company) {
            return '';
        }

        $logo = $company->getLogo();
        if ($logo === null) {
            return '';
        }

        $path = $tenantName . '/' . EfactConstants::IMAGE_DIR . '/' . $logo->getPath();

        return $this->s3UrlResolver->resolve($path);
    }
}
