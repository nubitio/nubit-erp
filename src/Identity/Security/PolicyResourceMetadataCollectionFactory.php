<?php

declare(strict_types=1);

namespace App\Identity\Security;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Override;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;

/**
 * Gives every operation that has no `security` the one {@see ResourceAccessPolicy} assigns it (rules in config/packages/resource_access.yaml).
 *
 * It runs on top of the whole metadata chain, so by now the default operations of a resource
 * (the six an `#[ApiResource]` without `operations:` gets) exist, and what an entity wrote itself
 * is already there: only a missing `security` is filled in, never an existing one replaced.
 */
#[AsDecorator('api_platform.metadata.resource.metadata_collection_factory')]
final readonly class PolicyResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private ResourceAccessPolicy $policy,
    ) {}

    #[Override]
    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $collection = $this->decorated->create($resourceClass);

        foreach ($collection as $index => $resource) {
            $operations = $resource->getOperations();
            if ($operations === null) {
                continue;
            }

            $guarded = [];
            foreach ($operations as $name => $operation) {
                if ($operation instanceof HttpOperation && $operation->getSecurity() === null) {
                    $expression = $this->policy->expressionFor(
                        $resourceClass,
                        $operation->getMethod() ?? 'GET',
                        $operation->getUriTemplate(),
                    );
                    if ($expression !== null) {
                        $operation = $operation->withSecurity($expression);
                    }
                }
                $guarded[$name] = $operation;
            }

            $collection[$index] = $resource->withOperations(new Operations($guarded));
        }

        return $collection;
    }
}
