<?php

declare(strict_types=1);

namespace App\Sales\Service;

use App\Catalog\Entity\PaymentMethod;
use App\Efact\Domain\ActiveReferenceGuard;
use App\Efact\Domain\Money;
use App\Identity\Entity\User;
use App\Inventory\Entity\Warehouse;
use App\Organization\Entity\Branch;
use App\Sales\Domain\DocType;
use App\Sales\Dto\SaleQuoteConvertDto;
use App\Sales\Entity\SaleItemRecord;
use App\Sales\Entity\SaleQuote;
use App\Sales\Entity\SaleRecord;
use App\Sales\State\SaleUtils;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Exception\ValidationException;

/**
 * Converts a SaleQuote into a real SaleRecord. Builds the new SaleRecord
 * directly (persist/flush), the same additive way
 * App\Billing\Recurring\Command\GenerateRecurringSalesCommand::buildSaleForPeriod()
 * creates administrative sales — never through SalesWriteGateway::write()
 * or SaleProcessor::process(), which are built around cleaning up and
 * reconstructing payments/taxes/discounts a fresh quote conversion
 * doesn't have.
 */
readonly class SaleQuoteConversionService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SaleUtils $saleUtils,
        private ActiveReferenceGuard $activeReferenceGuard,
    ) {}

    public function convert(SaleQuote $quote, SaleQuoteConvertDto $dto, User $user): SaleRecord
    {
        if ($quote->getStatus() === SaleQuote::STATUS_CONVERTED) {
            throw ValidationException::withErrors([
                'status' => ['La cotización ya fue convertida a venta.'],
            ]);
        }

        $validUntil = $quote->getValidUntil();
        if (
            $quote->getStatus() === SaleQuote::STATUS_EXPIRED
            || $validUntil !== null && $validUntil < $this->todayInLima()
        ) {
            throw ValidationException::withErrors([
                'status' => ['La cotización venció y no se puede convertir a venta.'],
            ]);
        }

        if ($quote->getItems()->isEmpty()) {
            throw ValidationException::withErrors([
                'items' => ['La cotización no tiene ítems.'],
            ]);
        }

        $warehouse = $dto->warehouseId !== null
            ? $this->entityManager->find(Warehouse::class, $dto->warehouseId)
            : $quote->getWarehouse();
        if (!$warehouse instanceof Warehouse) {
            throw ValidationException::withErrors([
                'warehouse' => ['Debe indicar un almacén para convertir la cotización.'],
            ]);
        }

        $branch = $dto->branchId !== null
            ? $this->entityManager->find(Branch::class, $dto->branchId)
            : $quote->getBranch();
        if (!$branch instanceof Branch) {
            throw ValidationException::withErrors([
                'branch' => ['Debe indicar una sucursal para convertir la cotización.'],
            ]);
        }

        $paymentMethodId = $dto->paymentMethodId ?? PaymentMethod::CASH;
        $paymentMethod = $this->entityManager->find(PaymentMethod::class, $paymentMethodId);
        if (!$paymentMethod instanceof PaymentMethod) {
            throw ValidationException::withErrors([
                'paymentMethodId' => ['Método de pago no encontrado.'],
            ]);
        }

        $this->activeReferenceGuard->assertActiveOnCreate($quote->getCustomer(), 'customer', 'El cliente');

        $today = $this->todayInLima();

        $sale = new SaleRecord();
        $sale->setCustomer($quote->getCustomer());
        $sale->setBranch($branch);
        $sale->setWarehouse($warehouse);
        $sale->setSellerUserId($user->getId());
        $sale->setPaymentMethod($paymentMethod);
        $sale->setStatus('COMPLETED');
        $sale->setCurrency($quote->getCurrency());
        $sale->setIssuedAt(\DateTime::createFromImmutable($today));
        $sale->setIssuedDate($today);
        $sale->setNote(sprintf('Convertida desde cotización %s', $quote->getId()));

        $subtotal = Money::money('0.00');
        $discountTotal = Money::money('0.00');
        $taxTotal = Money::money('0.00');

        foreach ($quote->getItems() as $quoteItem) {
            $item = new SaleItemRecord();
            $item->setLineNo($quoteItem->getLineNo());
            $item->setProduct($quoteItem->getProduct());
            $item->setQty($quoteItem->getQty());
            $item->setUnitPrice($quoteItem->getUnitPrice());
            $item->setDiscountPercent($quoteItem->getDiscountPercent());
            $item->setGrossAmount($quoteItem->getGrossAmount());
            $item->setDiscountAmount($quoteItem->getDiscountAmount());
            $item->setTaxAmount($quoteItem->getTaxAmount());
            $item->setNetAmount($quoteItem->getNetAmount());
            $item->setNameSnapshot($quoteItem->getNameSnapshot());
            $item->setSkuSnapshot($quoteItem->getSkuSnapshot());
            $item->setUomSnapshot($quoteItem->getUomSnapshot());

            $sale->addSaleItemRecord($item);

            $subtotal = $subtotal->plus(Money::money($quoteItem->getGrossAmount()));
            $discountTotal = $discountTotal->plus(Money::money($quoteItem->getDiscountAmount()));
            $taxTotal = $taxTotal->plus(Money::money($quoteItem->getTaxAmount()));
        }

        $grandTotal = $subtotal->minus($discountTotal)->plus($taxTotal);

        $sale->setSubtotal(Money::toString($subtotal));
        $sale->setDiscountTotal(Money::toString($discountTotal));
        $sale->setTaxTotal(Money::toString($taxTotal));
        $sale->setGrandTotal(Money::toString($grandTotal));
        $sale->setPaidTotal('0.00');
        $sale->setBalanceDue(Money::toString($grandTotal));

        $sale = $this->saleUtils->fillSaleValues($sale, DocType::NOTA_VENTA);

        $this->entityManager->wrapInTransaction(function () use ($sale, $quote): void {
            $this->entityManager->persist($sale);
            $quote->setStatus(SaleQuote::STATUS_CONVERTED);
            $quote->setConvertedSaleId($sale->getId());
            $quote->setConvertedAt(new DateTimeImmutable());
            $this->entityManager->flush();
        });

        return $sale;
    }

    /**
     * "Today" per the server's clock (UTC) can already be tomorrow relative
     * to Lima's calendar day, and vice versa — `validUntil` is a plain
     * calendar date with no timezone semantics of its own (stored as
     * midnight UTC by Doctrine's DATE_IMMUTABLE type), so it must be
     * compared against Lima's current calendar date, not the server's.
     * Returned as a midnight-UTC DateTimeImmutable so it compares correctly
     * against `validUntil` (same representation).
     */
    private function todayInLima(): DateTimeImmutable
    {
        $limaNow = new DateTimeImmutable('now', new \DateTimeZone('America/Lima'));

        return new DateTimeImmutable($limaNow->format('Y-m-d'));
    }
}
