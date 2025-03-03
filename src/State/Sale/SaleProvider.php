<?php

namespace App\State\Sale;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
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
    #[\Override]
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
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    private function buildContextForInbound(array $context): array
    {
        $context = OptionUtils::addSort($context, new SortRule('date_created', 'desc'));
        $context = OptionUtils::addFilter($context, new FilterRule('numero', FilterOperation::IsNull));

        return OptionUtils::addFilter($context, new FilterRule('tipo_id', FilterOperation::Equals, 1));
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    private function buildContextForOutbound(array $context): array
    {
        $context = OptionUtils::addSort($context, new SortRule('date_created', 'desc'));
        $context = OptionUtils::addFilter($context, new FilterRule('numero', FilterOperation::IsNull));

        return OptionUtils::addFilter($context, new FilterRule('tipo_id', FilterOperation::Equals, 2));
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    private function buildContextForSale(array $context): array
    {
        $context = OptionUtils::addSort($context, new SortRule('date_created', 'desc'));
        $context = OptionUtils::addFilter($context, new FilterRule('numero', FilterOperation::GreaterThan, 0));

        return OptionUtils::addFilter($context, new FilterRule('tipo_id', FilterOperation::Equals, 2));
    }
}
