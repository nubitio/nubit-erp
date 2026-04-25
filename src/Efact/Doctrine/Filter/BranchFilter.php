<?php

declare(strict_types=1);

namespace App\Efact\Doctrine\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Doctrine SQL Filter that automatically filters entities by branch_id.
 *
 * This filter is applied automatically to all queries on entities that have
 * a 'branch' association, ensuring users only see data from their selected branch.
 */
class BranchFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        // Check if the entity has a 'branch' association
        if (!$targetEntity->hasAssociation('branch')) {
            return '';
        }

        try {
            $branchId = $this->getParameter('branchId');
        } catch (\InvalidArgumentException) {
            // Parameter not set, don't filter
            return '';
        }

        // Get the column name for the branch association
        $association = $targetEntity->getAssociationMapping('branch');
        $columnName = $association['joinColumns'][0]['name'] ?? 'branch_id';

        return sprintf('%s.%s = %s', $targetTableAlias, $columnName, $branchId);
    }
}
