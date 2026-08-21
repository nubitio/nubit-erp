<?php

declare(strict_types=1);

namespace App\Sales\State;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use App\Sales\Service\SaleQuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Wraps the standard ORM provider and lazily flips SaleQuote.status to
 * "expired" for any row past its validUntil date, right before returning
 * results — avoids a scheduled command for a feature with no proactive
 * notification requirement (see Fase 2 plan).
 *
 * @implements ProviderInterface<object>
 */
readonly class SaleQuoteProvider implements ProviderInterface
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
        private SaleQuoteService $saleQuoteService,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            $paginator = $this->collectionProvider->provide($operation, $uriVariables, $context);

            $quotes = [];
            foreach ($paginator as $quote) {
                if ($quote instanceof SaleQuote) {
                    $quotes[] = $quote;
                }
            }

            $this->expireStaleQuotes($quotes);
            $this->detachMissingProducts($quotes);

            return $paginator;
        }

        $quote = $this->itemProvider->provide($operation, $uriVariables, $context);

        if ($quote instanceof SaleQuote) {
            $this->expireStaleQuotes([$quote]);
            $this->detachMissingProducts([$quote]);
        }

        return $quote;
    }

    /** @param SaleQuote[] $quotes */
    private function expireStaleQuotes(array $quotes): void
    {
        $expired = false;
        foreach ($quotes as $quote) {
            if ($this->saleQuoteService->expireIfPastDue($quote)) {
                $expired = true;
            }
        }

        if ($expired) {
            $this->entityManager->flush();
        }
    }

    /** @param SaleQuote[] $quotes */
    private function detachMissingProducts(array $quotes): void
    {
        foreach ($quotes as $quote) {
            foreach ($quote->getItems() as $item) {
                if (!$item instanceof SaleQuoteItem) {
                    continue;
                }

                $product = $item->getProduct();
                if ($product === null) {
                    continue;
                }

                try {
                    $product->getName();
                } catch (EntityNotFoundException) {
                    $item->setProduct(null);
                }
            }
        }
    }
}
