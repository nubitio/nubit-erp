<?php

declare(strict_types=1);

namespace App\Efact\Doctrine\Filter;

use App\Entity\Media;
use App\Entity\Purchase;
use App\Entity\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

class SoftDeleteFilter extends SQLFilter
{
    private const SOFT_DELETABLE_ENTITIES = [
        Purchase::class,
        User::class,
        Media::class,
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!in_array($targetEntity->getName(), self::SOFT_DELETABLE_ENTITIES, true)) {
            return '';
        }

        return sprintf('%s.deleted_at IS NULL', $targetTableAlias);
    }
}
