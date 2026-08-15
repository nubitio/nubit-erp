<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\Product;
use App\Efact\Domain\Money;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleQuoteItem;
use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;

/**
 * CRUD-adjacent business logic for SaleQuote: building items from the
 * writeItems payload, recomputing totals, and guarding status
 * transitions. Mirrors the item-building logic in
 * App\Sales\State\SaleProcessor::buildSaleItems() but simplified — quotes
 * have no offline/sync fields.
 */
readonly class SaleQuoteService
{
    /** Forward-only transition graph; a status can only move to the states listed. */
    private const array ALLOWED_TRANSITIONS = [
        SaleQuote::STATUS_DRAFT => [SaleQuote::STATUS_SENT, SaleQuote::STATUS_ACCEPTED],
        SaleQuote::STATUS_SENT => [SaleQuote::STATUS_ACCEPTED],
        SaleQuote::STATUS_ACCEPTED => [],
        SaleQuote::STATUS_EXPIRED => [],
        SaleQuote::STATUS_CONVERTED => [],
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param array<int, array<string, mixed>> $writeItems
     * @return list<SaleQuoteItem>
     */
    public function buildItemsFromPayload(array $writeItems): array
    {
        $items = [];
        $lineNo = 1;

        foreach ($writeItems as $itemData) {
            $item = new SaleQuoteItem();

            $productId = $this->extractProductId($itemData['product'] ?? null);
            $product = null;
            if ($productId !== null) {
                $product = $this->entityManager->find(Product::class, $productId);
                $item->setProduct($product);
            }

            $qty = (float) ($itemData['qty'] ?? 1);
            $unitPrice = $itemData['unitPrice'] ?? '0.00';
            $discountPercent = $itemData['discountPercent'] ?? null;
            $discount = '0.00';

            if ($discountPercent !== null) {
                $percentValue = (float) $discountPercent;
                if ($percentValue < 0 || $percentValue > 100) {
                    throw ValidationException::withErrors([
                        'discountPercent' => [sprintf(
                            'El porcentaje de descuento debe estar entre 0 y 100. Valor recibido: %s.',
                            $discountPercent,
                        )],
                    ]);
                }

                $discountAmount = Money::money(BigDecimal::of((string) $unitPrice)->multipliedBy(
                    $qty,
                )->multipliedBy(Money::rate($discountPercent)));
                $discount = Money::toString($discountAmount);
                $item->setDiscountPercent((string) $discountPercent);
            }

            $grossAmount = Money::money(BigDecimal::of((string) $unitPrice)->multipliedBy($qty));
            $netAmount = Money::toString(Money::money($grossAmount)->minus(Money::money($discount)));

            $item->setLineNo($lineNo++);
            $item->setQty(number_format($qty, 3, '.', ''));
            $item->setUnitPrice(number_format((float) $unitPrice, 4, '.', ''));
            $item->setGrossAmount(Money::toString($grossAmount));
            $item->setDiscountAmount($discount);
            $item->setTaxAmount('0.00');
            $item->setNetAmount($netAmount);

            $description = trim((string) ($itemData['nameSnapshot'] ?? ''));
            if ($description !== '') {
                $item->setNameSnapshot(mb_substr($description, 0, 255));
            }

            if ($product !== null) {
                if ($item->getNameSnapshot() === '') {
                    $item->setNameSnapshot($product->getName());
                }
                $item->setSkuSnapshot($product->getCode());
                $item->setUomSnapshot($product->getUnitOfMeasure()->getCode());
            }

            $items[] = $item;
        }

        return $items;
    }

    /**
     * Recomputes subtotal/discountTotal/taxTotal/grandTotal from the
     * quote's current items — never trusts a client-supplied total.
     */
    public function recomputeTotals(SaleQuote $quote): void
    {
        $subtotal = Money::money('0.00');
        $discountTotal = Money::money('0.00');
        $taxTotal = Money::money('0.00');

        foreach ($quote->getItems() as $item) {
            $subtotal = $subtotal->plus(Money::money($item->getGrossAmount()));
            $discountTotal = $discountTotal->plus(Money::money($item->getDiscountAmount()));
            $taxTotal = $taxTotal->plus(Money::money($item->getTaxAmount()));
        }

        $grandTotal = $subtotal->minus($discountTotal)->plus($taxTotal);

        $quote->setSubtotal(Money::toString($subtotal));
        $quote->setDiscountTotal(Money::toString($discountTotal));
        $quote->setTaxTotal(Money::toString($taxTotal));
        $quote->setGrandTotal(Money::toString($grandTotal));
    }

    public function transitionTo(SaleQuote $quote, string $newStatus): void
    {
        $allowed = self::ALLOWED_TRANSITIONS[$quote->getStatus()] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw ValidationException::withErrors([
                'status' => [sprintf(
                    'No se puede pasar la cotización de "%s" a "%s".',
                    $quote->getStatus(),
                    $newStatus,
                )],
            ]);
        }

        $quote->setStatus($newStatus);
    }

    public function expireIfPastDue(SaleQuote $quote): bool
    {
        $expirable = [SaleQuote::STATUS_DRAFT, SaleQuote::STATUS_SENT, SaleQuote::STATUS_ACCEPTED];
        $validUntil = $quote->getValidUntil();

        if ($validUntil === null || !in_array($quote->getStatus(), $expirable, true)) {
            return false;
        }

        if ($validUntil >= $this->todayInLima()) {
            return false;
        }

        $quote->setStatus(SaleQuote::STATUS_EXPIRED);

        return true;
    }

    /**
     * "Today" per the server's clock (UTC) can already be tomorrow relative
     * to Lima's calendar day, and vice versa — `validUntil` is a plain
     * calendar date with no timezone semantics of its own (stored as
     * midnight UTC by Doctrine's DATE_IMMUTABLE type), so it must be
     * compared against Lima's current calendar date, not the server's.
     */
    private function todayInLima(): DateTimeImmutable
    {
        $limaNow = new DateTimeImmutable('now', new \DateTimeZone('America/Lima'));

        return new DateTimeImmutable($limaNow->format('Y-m-d'));
    }

    private function extractProductId(mixed $productRef): ?string
    {
        if ($productRef === null) {
            return null;
        }

        if (is_array($productRef)) {
            $productRef = $productRef['@id'] ?? $productRef['id'] ?? null;
            if ($productRef === null) {
                return null;
            }
        }

        if (!is_string($productRef)) {
            return null;
        }

        if (str_starts_with($productRef, '/api/products/')) {
            return substr($productRef, strlen('/api/products/'));
        }

        return $productRef;
    }
}
