<?php

namespace App\Sale\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use App\Core\Domain\Api\ApiProviderInterface;
use App\Core\Domain\DataLoader\FilterOperation;
use App\Core\Domain\DataLoader\FilterRule;
use App\Core\Domain\DataLoader\JoinRule;
use App\Core\Domain\DataLoader\JoinType;
use App\Core\Domain\DataLoader\OptionsBuilder;
use App\Core\Domain\DataLoader\OptionUtils;
use App\Core\Domain\DataLoader\SortRule;
use App\Sale\Domain\GetAllSaleUseCase;
use App\Sale\Domain\GetOneSaleUseCase;
use App\Sale\Domain\Sale;
use App\Sale\Infrastructure\SaleEntity;

/**
 * @implements ApiProviderInterface<Sale>
 */
readonly class SaleProvider implements ApiProviderInterface
{
    public function __construct(
        private GetAllSaleUseCase $getAllUseCase,
        private GetOneSaleUseCase $getOneUseCase,
    ) {
    }

    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $type = $context['filters']['type'] ?? null;
            match ($type) {
                'inbound' => $context = $this->buildContextForInbound($context),
                'outbound' => $context = $this->buildContextForOutbound($context),
                default => $context = $this->buildContextForSale($context),
            };
            $options = OptionsBuilder::build($context, SaleEntity::class, Sale::class);

            return $this->getAllUseCase->__invoke($options);
        }

        return $this->getOneUseCase->__invoke(strval($uriVariables['movimiento_id']));
    }

    /**
     * @param array<string, array<string, bool|float|int|string|null>> $context
     *
     * @return array<string, array<string, bool|float|int|string|null>>
     */
    private function buildContextForInbound(array $context): array
    {
        $context = OptionUtils::addJoin($context, new JoinRule('e.almacen', 'a'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.operacion', 'o'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.proveedor', 'p', JoinType::LEFT));
        $context = OptionUtils::addJoin($context, new JoinRule('e.details', 'sd'));
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
        $context = OptionUtils::addJoin($context, new JoinRule('e.almacen', 'a'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.operacion', 'o'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.details', 'sd'));
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
        $context = OptionUtils::addJoin($context, new JoinRule('e.cliente', 'c'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.price_list', 'pl', JoinType::LEFT));
        $context = OptionUtils::addJoin($context, new JoinRule('e.almacen', 'a'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.tipo_pago', 'pm'));
        $context = OptionUtils::addJoin($context, new JoinRule('e.details', 'sd'));
        $context = OptionUtils::addSort($context, new SortRule('date_created', 'desc'));
        $context = OptionUtils::addFilter($context, new FilterRule('numero', FilterOperation::GreaterThan, 0));

        return OptionUtils::addFilter($context, new FilterRule('tipo_id', FilterOperation::Equals, 2));
    }
}
