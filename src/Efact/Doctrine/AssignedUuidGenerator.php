<?php

declare(strict_types=1);

namespace App\Efact\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AbstractIdGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * Honors a UUID already assigned on the entity so inventory/cash side effects
 * share the persisted primary key. Symfony's UuidGenerator always mints a new
 * value and splits sales.id / goods_receipts.id from stock_movements.source_id.
 */
final class AssignedUuidGenerator extends AbstractIdGenerator
{
    public function generateId(EntityManagerInterface $em, object|null $entity): string
    {
        if (is_object($entity) && method_exists($entity, 'getId')) {
            $existing = $entity->getId();
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        return Uuid::v7()->toRfc4122();
    }
}
