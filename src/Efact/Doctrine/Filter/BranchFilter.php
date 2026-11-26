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
        // A subscription plan carries its own branch and warehouse because
        // they determine where the recurring sale is issued. Subscriptions
        // can therefore reference a plan created from another active branch;
        // filtering it here makes API Platform fail while resolving that IRI.
        // Series are company-level settings; issuance still explicitly selects
        // the branch of the document rather than reading all series.
        if (in_array($targetEntity->getTableName(), ['subscription_plan', 'local_comprobante'], true)) {
            return '';
        }

        // Check if the entity has a 'branch' association
        if (!$targetEntity->hasAssociation('branch')) {
            return '';
        }

        try {
            $branchIds = $this->getParameterList('branchIds');
        } catch (\InvalidArgumentException) {
            return '1 = 0';
        }

        if ($branchIds === '') {
            return '1 = 0';
        }

        // Get the column name for the branch association
        $association = $targetEntity->getAssociationMapping('branch');
        $columnName = $association['joinColumns'][0]['name'] ?? 'branch_id';

        return sprintf('%s.%s IN (%s)', $targetTableAlias, $columnName, $branchIds);
    }
}
