<?php

declare(strict_types=1);

namespace App\Inventory\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Catalog\Entity\Product;
use App\Inventory\Domain\PriceListChanges;
use App\Inventory\Dto\PriceItemDto;
use App\Inventory\Dto\PricesDto;
use App\Inventory\Entity\PriceList;
use App\Inventory\Entity\PriceListItem;
use App\Inventory\Repository\PriceListItemRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Nubit\AdminBundle\Audit\Entity\AuditLog;
use Nubit\Platform\Http\ApiResponse;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Throwable;

/**
 * POST /api/price-lists/update — saves the special prices of one price list.
 *
 * The save, its audit entry and nothing else happen in one transaction; once it is
 * committed the change is announced on Mercure so open tills and sale forms re-read
 * the tariff.
 *
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class PriceListProcessor implements ProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PriceListItemRepository $items,
        private HubInterface $mercureHub,
        private LoggerInterface $logger,
        private TokenStorageInterface $tokenStorage,
    ) {}

    /**
     * @inheritdoc
     *
     * @param PricesDto $data
     */
    #[Override]
    public function process(
        mixed $data,
        Operation $operation,
        array $uriVariables = [],
        array $context = [],
    ): ApiResponse {
        $this->entityManager->beginTransaction();
        try {
            $this->save($data);
            $this->entityManager->commit();
        } catch (Throwable $e) {
            $this->entityManager->rollback();
            $this->logger->error('price_list.save_failed', [
                'priceListId' => $data->priceListId,
                'exception' => $e->getMessage(),
            ]);

            return new ApiResponse(success: false, message: 'Error al actualizar los precios');
        }

        $this->announceChange($data->priceListId);

        return new ApiResponse(success: true, message: 'Precios actualizados correctamente');
    }

    private function save(PricesDto $data): void
    {
        $priceList = $this->entityManager->find(PriceList::class, $data->priceListId) ?? throw new DomainException(
            'Price list ' . $data->priceListId . ' does not exist.',
        );

        $productIds = array_values(array_filter(array_map(
            static fn(PriceItemDto $item): ?string => $item->productId,
            $data->prices,
        )));
        if ($productIds === []) {
            return;
        }

        /** @var array<string, Product> $products */
        $products = [];
        foreach ($this->entityManager->getRepository(Product::class)->findBy(['id' => $productIds]) as $product) {
            $products[(string) $product->getId()] = $product;
        }

        $existing = $this->items->byProduct($data->priceListId, $productIds);
        $before = array_map(static fn(PriceListItem $item): string => $item->getPrice(), $existing);

        foreach ($data->prices as $row) {
            $productId = (string) $row->productId;
            $product = $products[$productId] ?? throw new DomainException('Product ' . $productId . ' does not exist.');
            $price = (string) BigDecimal::of((string) $row->price)->toScale(4, RoundingMode::HALF_UP);

            if (isset($existing[$productId])) {
                $existing[$productId]->setPrice($price);
            } else {
                $this->entityManager->persist(new PriceListItem($priceList, $product, $price));
            }
        }

        $this->audit($data->priceListId, $before, $data->prices, $products);
        $this->entityManager->flush();
    }

    /**
     * One entry for the whole save, on the price list, with the products that changed.
     * Written in the same transaction as the prices: a tariff that changed has a trail,
     * and a trail without a change cannot exist.
     *
     * @param array<string, string> $before
     * @param array<PriceItemDto> $items
     * @param array<string, Product> $products
     */
    private function audit(int $priceListId, array $before, array $items, array $products): void
    {
        $names = array_map(static fn(Product $product): string => $product->getName(), $products);
        $changes = PriceListChanges::diff($before, $items, $names);
        if ($changes === []) {
            return;
        }

        $username = $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier() ?? 'system';
        $this->entityManager->persist(new AuditLog('pricelist', (string) $priceListId, 'update', $changes, $username));
    }

    /**
     * Tills and the sale form listen on `{origin}/api/price-lists/*` to re-read the tariff
     * they are selling with. A hub that is down must not undo a saved price list: the
     * clients poll as a backup.
     */
    private function announceChange(int $priceListId): void
    {
        try {
            $parts = parse_url($this->mercureHub->getPublicUrl());
            $scheme = $parts['scheme'] ?? null;
            $host = $parts['host'] ?? null;
            $port = $parts['port'] ?? null;
            $origin =
                $scheme !== null && $host !== null ? $scheme . '://' . $host . ($port !== null ? ':' . $port : '') : '';

            $this->mercureHub->publish(
                new Update($origin . '/api/price-lists/' . $priceListId, json_encode([
                    'id' => $priceListId,
                ], JSON_THROW_ON_ERROR)),
            );
        } catch (Throwable $e) {
            $this->logger->warning('price_list.mercure_publish_failed', [
                'priceListId' => $priceListId,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
