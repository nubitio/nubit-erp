<?php

namespace App\State\Sale;

use Override;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class SaleProvider implements ProviderInterface
{
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
            $type = $context['filters']['type'] ?? null;
            match ($type) {
                'inbound' => $context = $this->buildContextForInbound($context),
                'outbound' => $context = $this->buildContextForOutbound($context),
                default => $context = $this->buildContextForSale($context),
            };

            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * Build context for inbound sales (tipo_id = 1, no invoice number)
     */
    private function buildContextForInbound(array $context): array
    {
        // Add sort: date_created DESC
        $context = $this->addSort($context, 'date_created', 'desc');

        // Add filters: numero IS NULL AND tipo_id = 1
        $context = $this->addFilters($context, [
            ['numero', 'isnull', null],
            'and',
            ['tipo_id', '=', 1],
        ]);

        return $context;
    }

    /**
     * Build context for outbound sales (tipo_id = 2, no invoice number)
     */
    private function buildContextForOutbound(array $context): array
    {
        // Add sort: date_created DESC
        $context = $this->addSort($context, 'date_created', 'desc');

        // Add filters: numero IS NULL AND tipo_id = 2
        $context = $this->addFilters($context, [
            ['numero', 'isnull', null],
            'and',
            ['tipo_id', '=', 2],
        ]);

        return $context;
    }

    /**
     * Build context for regular sales (tipo_id = 2, with invoice number)
     */
    private function buildContextForSale(array $context): array
    {
        // Add sort: date_created DESC
        $context = $this->addSort($context, 'date_created', 'desc');

        // Add filters: numero > 0 AND tipo_id = 2
        $context = $this->addFilters($context, [
            ['numero', '>', 0],
            'and',
            ['tipo_id', '=', 2],
        ]);

        return $context;
    }

    /**
     * Add sort to context in DevExtreme format
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
     * Add filters to context in DevExtreme format
     */
    private function addFilters(array $context, array $newFilters): array
    {
        if (!isset($context['filters']['filter'])) {
            $context['filters']['filter'] = [];
        }

        // If existing filters, combine with AND
        if (!empty($context['filters']['filter'])) {
            $context['filters']['filter'] = [
                $context['filters']['filter'],
                'and',
                $newFilters,
            ];
        } else {
            $context['filters']['filter'] = $newFilters;
        }

        return $context;
    }
}
