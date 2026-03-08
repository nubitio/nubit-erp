<?php

declare(strict_types=1);

namespace App\State\Sale;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\SaleRecord;
use App\Repository\PaymentMethodRepository;
use App\Repository\SaleRecordRepository;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Provider for the V2 sales endpoint (/api/sales).
 *
 * Wraps the standard ORM provider and enriches each SaleRecord with
 * tax_document data (invoice number, SUNAT status, receipt URL) and
 * primary payment method — all via batched DBAL queries to avoid N+1.
 *
 * @implements ProviderInterface<object>
 */
readonly class SaleV2Provider implements ProviderInterface
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
        private SaleRecordRepository $saleRecordRepository,
        private PaymentMethodRepository $paymentMethodRepository,
    ) {
    }

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $context = $this->buildCollectionContext($context);
            $paginator = $this->collectionProvider->provide($operation, $uriVariables, $context);

            $records = [];
            foreach ($paginator as $record) {
                if ($record instanceof SaleRecord) {
                    $records[] = $record;
                }
            }

            $this->enrichRecords($records);

            return $paginator;
        }

        $record = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($record instanceof SaleRecord) {
            $this->enrichRecords([$record]);
        }

        return $record;
    }

    /**
     * Apply default sort (newest first) for the collection.
     *
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function buildCollectionContext(array $context): array
    {
        if (!isset($context['filters']['order'])) {
            $context['filters']['order'] = ['createdAt' => 'DESC'];
        }

        return $context;
    }

    /**
     * @param SaleRecord[] $records
     */
    private function enrichRecords(array $records): void
    {
        $this->saleRecordRepository->enrichWithTaxDocuments($records);
        $this->saleRecordRepository->enrichWithPaymentMethods($records, $this->paymentMethodRepository);
    }
}
