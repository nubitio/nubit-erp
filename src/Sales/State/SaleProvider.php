<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProviderInterface<object>
 */
readonly class SaleProvider implements ProviderInterface
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

    /**
     * @inheritdoc
     */
    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $type = $context['filters']['type'] ?? null;
            match ($type) {
                'inbound', 'purchases' => $context = $this->buildContextForInbound($context),
                'outbound' => $context = $this->buildContextForOutbound($context),
                default => $context = $this->buildContextForSale($context),
            };

            return $this->collectionProvider->provide($operation, $uriVariables, $context);
        }

        return $this->itemProvider->provide($operation, $uriVariables, $context);
    }

    /**
     * Build context for inbound sales (tipo_id = 1, no invoice number)
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContextForInbound(array $context): array
    {
        $context = $this->addSort($context, 'date_created', 'desc');

        $context = $this->addFilters($context, [
            ['number', 'isnull', null],
            'and',
            ['tipo_id', '=', 1],
        ]);

        return $context;
    }

    /**
     * Build context for outbound sales (tipo_id = 2, no invoice number)
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContextForOutbound(array $context): array
    {
        $context = $this->addSort($context, 'date_created', 'desc');

        $context = $this->addFilters($context, [
            ['number', 'isnull', null],
            'and',
            ['tipo_id', '=', 2],
        ]);

        return $context;
    }

    /**
     * Build context for regular sales (tipo_id = 2, with invoice number)
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildContextForSale(array $context): array
    {
        $context = $this->addSort($context, 'date_created', 'desc');

        $context = $this->addFilters($context, [
            ['number', '>', 0],
            'and',
            ['tipo_id', '=', 2],
        ]);

        return $context;
    }

    /**
     * Add sort to context in the data-grid filter format.
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

    /**
     * Add filters to context in the data-grid filter format.
     *
     * @param array<string, mixed> $context
     * @param array<int, mixed> $newFilters
     * @return array<string, mixed>
     */
    private function addFilters(array $context, array $newFilters): array
    {
        if (!isset($context['filters']['filter'])) {
            $context['filters']['filter'] = [];
        }

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
