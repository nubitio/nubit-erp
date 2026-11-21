<?php

declare(strict_types=1);

namespace App\Tests\Identity\Security;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;

/** Every operation the kernel exposes, as the framework sees it (after the access policy is applied). */
final readonly class ApiOperationInventory
{
    /** Routes the framework registers only to build IRIs or render errors; the router answers them 404. */
    private const array INERT_CONTROLLERS = ['api_platform.action.not_exposed', 'api_platform.action.placeholder'];

    /** @param list<class-string> $exempt resources outside the policy on purpose */
    public function __construct(
        private ResourceNameCollectionFactoryInterface $names,
        private ResourceMetadataCollectionFactoryInterface $metadata,
        private array $exempt,
    ) {}

    /** @return list<array{class-string, HttpOperation}> */
    public function operations(): array
    {
        $operations = [];
        /** @var iterable<class-string> $classes */
        $classes = $this->names->create();
        foreach ($classes as $class) {
            if (!$this->isInPolicy($class)) {
                continue;
            }
            foreach ($this->operationsOf($class) as $operation) {
                $operations[] = [$class, $operation];
            }
        }

        return $operations;
    }

    /** @return list<string> the operations still without `security` */
    public function unguarded(): array
    {
        $open = [];
        foreach ($this->operations() as [$class, $operation]) {
            if ($operation->getSecurity() === null) {
                $open[] = sprintf(
                    '%s %s %s',
                    $class,
                    $operation->getMethod(),
                    $operation->getUriTemplate() ?? '(default)',
                );
            }
        }

        return $open;
    }

    private function isInPolicy(string $class): bool
    {
        return !str_starts_with($class, 'ApiPlatform\\') && !\in_array($class, $this->exempt, true);
    }

    /**
     * @param class-string $class
     *
     * @return list<HttpOperation>
     */
    private function operationsOf(string $class): array
    {
        $found = [];
        foreach ($this->metadata->create($class) as $resource) {
            /** @var iterable<string, Operation> $operations */
            $operations = $resource->getOperations() ?? [];
            foreach ($operations as $operation) {
                if (
                    $operation instanceof HttpOperation
                    && !\in_array($operation->getController(), self::INERT_CONTROLLERS, true)
                ) {
                    $found[] = $operation;
                }
            }
        }

        return $found;
    }
}
