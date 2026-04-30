<?php

declare(strict_types=1);

namespace App\Billing\Invoice\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<object>
 */
readonly class InvoiceProvider implements ProviderInterface
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
    ) {
    }

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object | array | null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = $this->addSort($context, 'createdAt', 'desc');

            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * Add sort to context in DevExtreme format
     *
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
}
