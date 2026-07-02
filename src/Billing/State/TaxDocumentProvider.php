<?php

declare(strict_types=1);

namespace App\Billing\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<object>
 */
readonly class TaxDocumentProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $collectionProvider
     * @param ProviderInterface<object> $itemProvider
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.collection_provider')]
        private ProviderInterface $collectionProvider,
        #[Autowire(service: 'api_platform.doctrine.orm.state.item_provider')]
        private ProviderInterface $itemProvider,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = $this->addSort($context, 'createdAt', 'desc');
            $type = $context['filters']['type'] ?? null;
            $context = $this->addTypeFilterByOperation($type, $context);

            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function addSort(array $context, string $selector, string $direction = 'asc'): array
    {
        if (!isset($context['filters']['sort'])) {
            $context['filters']['sort'] = [];
        }

        $context['filters']['sort'][] = [
            'selector' => $selector,
            'desc' => $direction === 'desc',
        ];

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function addTypeFilterByOperation(?string $type, array $context): array
    {
        $typeFilter = match ($type) {
            'invoice' => ['documentType', 'in', ['01', '03']],
            'note' => ['documentType', 'in', ['07', '08']],
            default => null,
        };

        if ($typeFilter === null) {
            return $context;
        }

        if (!isset($context['filters']['filter']) || $context['filters']['filter'] === []) {
            $context['filters']['filter'] = [$typeFilter];

            return $context;
        }

        $context['filters']['filter'] = [
            $context['filters']['filter'],
            'and',
            [$typeFilter],
        ];

        return $context;
    }
}
