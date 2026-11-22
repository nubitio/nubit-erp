<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Repository\TaxDocumentRepository;
use App\Settings\Service\TenantSettingsService;
use Brick\Math\Exception\MathException;
use Nubit\Platform\Exception\ServiceException;

/**
 * Turns the lines a user asked to return into authoritative credit-note lines and
 * totals, re-derived from the affected document. Shared by the real issuance
 * (TaxDocumentProcessor) and the unsaved preview so both always agree.
 */
readonly class CreditNoteLinePreparer
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private TenantSettingsService $tenantSettingsService,
        private CreditNoteReturnSelector $selector,
    ) {}

    /**
     * @throws ServiceException
     * @throws MathException
     */
    public function prepare(TaxDocument $data): void
    {
        $related = $data->getRelatedTaxDocument() ?? throw new ServiceException(
            'Debe seleccionar el comprobante afectado.',
        );

        $selected = $this->selector->select($data, $related);

        // Source percentages from related TaxDocument (v2-authoritative path)
        $firstLine = $related->getLines()->first();
        $taxPercent =
            ($firstLine !== false ? $firstLine->getTaxPercentage() : null)
            ?? (string) $this->tenantSettingsService->getIgv();

        $amounts = new InvoiceCalculator(
            array_map(
                static fn(array $item): InvoiceLineInput => new InvoiceLineInput(
                    quantity: (string) $item['quantity'],
                    unitPrice: (string) $item['original']->getUnitPrice(),
                ),
                $selected,
            ),
            $related->getDiscountPercent() ?? '0',
            $taxPercent,
            '0',
        )->calculate();

        $amountLines = $amounts->getLines();
        foreach ($selected as $index => $item) {
            if (isset($amountLines[$index])) {
                $data->addLine($this->buildLine($item, $amountLines[$index], $taxPercent));
            }
        }

        $this->copyHeader($data, $related);
        $data->setTotal($amounts->getTotal());
        $data->setSubtotal($amounts->getSubtotal());
        $data->setTaxableAmount($amounts->getTaxedAmount());
        $data->setIgvAmount($amounts->getTax());
        $data->setRcAmount($amounts->getChargesAmount());
        $data->setDiscountAmount($amounts->getDiscountAmount());
    }

    /**
     * @param array{original: TaxDocumentLine, quantity: float, restock: bool} $item
     */
    private function buildLine(array $item, InvoiceLineAmounts $amountLine, string $taxPercent): TaxDocumentLine
    {
        $original = $item['original'];
        $line = new TaxDocumentLine();
        $line->setLineNumber($original->getLineNumber());
        $line->setProductId($original->getProductId());
        $line->setProductCode($original->getProductCode());
        $line->setProductName($original->getProductName());
        $line->setProductUom($original->getProductUom());
        $line->setQuantity($amountLine->quantity);
        $line->setUnitPrice($amountLine->unitPrice);
        $line->setUnitValue($amountLine->unitValue);
        $line->setDiscountAmount($amountLine->discountAmount);
        $line->setTaxedAmount($amountLine->taxableAmount);
        $line->setTaxAmount($amountLine->taxAmount);
        $line->setTaxPercentage($taxPercent);
        $line->setTaxType($original->getTaxType() ?? '10');
        $line->setTotal($amountLine->totalAmount);
        $line->setRestock($item['restock']);

        return $line;
    }

    /** Currency, exchange rate, payment and detraction data are inherited from the affected document. */
    private function copyHeader(TaxDocument $data, TaxDocument $related): void
    {
        // Prefer the related TaxDocument's columns; fall back to the sales table lookup.
        $saleCurrency = $this->taxDocumentRepository->getSalesCurrencyForSaleId($related->getCanonicalSaleId());
        $currency = $related->getCurrency() ?? $saleCurrency['currency'] ?? 'PEN';
        $exchangeRate = $currency === 'PEN'
            ? '1.000000'
            : $related->getExchangeRate() ?? $saleCurrency['exchange_rate'];
        if ($exchangeRate === null || $exchangeRate === '') {
            throw new ServiceException('No se pudo determinar el tipo de cambio para el documento');
        }

        $data->setCurrency($currency);
        $data->setExchangeRate($exchangeRate);
        $data->setPaymentMethod($related->getPaymentMethod());
        $data->setDiscountPercent($related->getDiscountPercent() ?? '0');
        $data->setOperationType($related->getOperationType());
        $data->setDetractionCode($related->getDetractionCode());
        $data->setDetractionPercent($related->getDetractionPercent());
        $data->setDetractionAmount($related->getDetractionAmount());
        $data->setDetractionPaymentMethod($related->getDetractionPaymentMethod());
        $data->setDetractionValueRef($related->getDetractionValueRef());
    }
}
