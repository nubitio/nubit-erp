<?php

declare(strict_types=1);

namespace App\State\TaxDocument;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Organization\Repository\BranchRepository;
use App\Billing\Repository\TaxDocumentRepository;
use App\EInvoicing\Service\InvoiceCalculator;
use App\EInvoicing\Service\InvoiceLineInput;
use App\State\Sale\SalesWriteGateway;
use Brick\Math\Exception\MathException;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class TaxDocumentProcessor implements ProcessorInterface
{
    /**
     * Reason codes that represent a full cancellation of the affected document.
     * When a credit note is created with one of these codes, the linked sale is
     * automatically marked as VOIDED (same behaviour as a Comunicación de Baja).
     *
     * SUNAT catalog 09:
     *   01 — Anulación de la operación
     *   02 — Anulación por error en el RUC
     *   06 — Devolución total
     */
    private const VOID_REASON_CODES = ['01', '02', '06'];

    /**
     * @param ProcessorInterface<mixed, mixed> $persistProcessor
     * @param ProcessorInterface<mixed, mixed> $removeProcessor
     */
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        #[Autowire(service: 'api_platform.doctrine.orm.state.remove_processor')]
        private ProcessorInterface $removeProcessor,
        private BranchContextService $branchContextService,
        private BranchRepository $branchRepository,
        private TaxDocumentRepository $taxDocumentRepository,
        private SalesWriteGateway $salesWriteGateway,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @inheritdoc
     *
     * @param TaxDocument $data
     *
     * @throws ServiceException
     */
    #[Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $isNew = !$data->getId();

        if ($operation instanceof DeleteOperationInterface) {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede eliminar un documento procesado.');
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($isNew && $data->getReasonCode() != null) {
            $related = $data->getRelatedTaxDocument();
            $data->setDocumentType('07');
            $data->setAffectedDocument($related->getFullNumber());
            $data->setCustomer($related->getCustomer());
            $data->setTotal($related->getTotal());
            if ($related->isVoided()) {
                throw new ServiceException('No se puede generar nota de crédito para un comprobante anulado.');
            }
            $branchId = $this->branchContextService->getCurrentBranchId();
            $affectedSeries = substr($data->getAffectedDocument(), 0, 4);
            $seriesResult = $this->taxDocumentRepository->getTaxDocumentSeriesByBranch($branchId, '07', $affectedSeries);
            $number = $this->taxDocumentRepository->getNextSequenceNumber($branchId, '07');
            if ($seriesResult === null || $seriesResult->associatedSeries != $affectedSeries) {
                throw new ServiceException(
                    'La serie de la nota de crédito no coincide con la serie del documento afectado.'
                );
            }
            $data->setSeries($seriesResult->series);
            $data->setNumber((string)$number);
            $this->prepareCreditNoteLines($data);

            // Propagate sale reference from related document to new credit note
            if ($related->getSaleRecord() !== null) {
                $data->setSaleRecord($related->getSaleRecord());
            }
        }

        if ($isNew) {
            $data->setCreatedAt(new DateTime());

            // Set the branch from the current user's session
            $branchId = $this->branchContextService->getCurrentBranchId();
            if ($branchId !== null) {
                $branch = $this->branchRepository->find($branchId);
                $data->setBranch($branch);
            }
        } else {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede actualizar un documento procesado.');
            }
            $data->setUpdatedAt(new DateTime());
        }

        $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);

        // If this is a new credit note with a void reason code, mark the linked sale as VOIDED.
        if ($isNew && $data->getReasonCode() !== null) {
            $this->voidSaleIfCreditNoteAnnuls($data);
        }

        return $result;
    }

    /**
     * @throws ServiceException
     * @throws MathException
     */
    private function prepareCreditNoteLines(TaxDocument $data): void
    {
        $related = $data->getRelatedTaxDocument();
        if ($related === null) {
            throw new ServiceException('Debe seleccionar el comprobante afectado.');
        }

        $requestedLines = [];
        foreach ($data->getLines() as $line) {
            $requestedLines[] = [
                'lineNumber' => $line->getLineNumber(),
                'quantity' => $line->getQuantity(),
            ];
        }

        foreach ($data->getLines()->toArray() as $line) {
            $data->removeLine($line);
        }

        if ($requestedLines === []) {
            throw new ServiceException('Debe seleccionar al menos un ítem para la nota de crédito.');
        }

        $originalLines = $related->getLines();

        if ($originalLines->isEmpty()) {
            throw new ServiceException('El comprobante afectado no tiene ítems registrados.');
        }

        $originalByLine = [];
        foreach ($originalLines as $line) {
            $originalByLine[$line->getLineNumber()] = $line;
        }

        $selected = [];
        foreach ($requestedLines as $req) {
            $lineNumber = (int)$req['lineNumber'];
            $requestedQty = (float)$req['quantity'];
            if ($requestedQty <= 0) {
                continue;
            }
            $original = $originalByLine[$lineNumber] ?? null;
            if (!$original instanceof TaxDocumentLine) {
                throw new ServiceException('El ítem seleccionado no existe en el comprobante afectado.');
            }
            $originalQty = (float)$original->getQuantity();
            if ($requestedQty > $originalQty) {
                throw new ServiceException('La cantidad a devolver no puede exceder la cantidad del comprobante.');
            }
            $selected[] = [
                'original' => $original,
                'quantity' => $requestedQty,
            ];
        }

        if ($selected === []) {
            throw new ServiceException('Debe seleccionar al menos un ítem para la nota de crédito.');
        }

        // Source percentages from related TaxDocument (v2-authoritative path)
        $discountPercent = $related->getDiscountPercent() ?? '0';
        $firstLine = $related->getLines()->first();
        $taxPercent = ($firstLine !== false ? $firstLine->getTaxPercentage() : null) ?? '18';
        $chargesPercent = '0';

        $lineInputs = array_map(
            static fn (array $item): InvoiceLineInput => new InvoiceLineInput(
                quantity: (string)$item['quantity'],
                unitPrice: (string)$item['original']->getUnitPrice()
            ),
            $selected
        );

        $calculator = new InvoiceCalculator($lineInputs, $discountPercent, $taxPercent, $chargesPercent);
        $amounts = $calculator->calculate();
        $amountLines = $amounts->getLines();

        foreach ($selected as $index => $item) {
            $amountLine = $amountLines[$index] ?? null;
            if ($amountLine === null) {
                continue;
            }
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
            $data->addLine($line);
        }

        $data->setTotal($amounts->getTotal());

        // Source currency: prefer related TaxDocument columns; fall back to sales table lookup
        $saleId = $related->getCanonicalSaleId();
        $saleCurrencyData = $this->taxDocumentRepository->getSalesCurrencyForSaleId($saleId);
        $currency = $related->getCurrency() ?? $saleCurrencyData['currency'] ?? 'PEN';
        $exchangeRate = $related->getExchangeRate() ?? $saleCurrencyData['exchange_rate'];
        if ($currency === 'PEN') {
            $exchangeRate = '1.000000';
        }
        if ($currency !== 'PEN' && ($exchangeRate === null || $exchangeRate === '')) {
            throw new ServiceException('No se pudo determinar el tipo de cambio para el documento');
        }
        $data->setCurrency($currency);
        $data->setExchangeRate($exchangeRate);

        // Source remaining fields from related TaxDocument (v2-authoritative path)
        $data->setPaymentMethod($related->getPaymentMethod());
        $data->setDiscountPercent($related->getDiscountPercent() ?? '0');
        $data->setOperationType($related->getOperationType());
        $data->setDetractionCode($related->getDetractionCode());
        $data->setDetractionPercent($related->getDetractionPercent());
        $data->setDetractionAmount($related->getDetractionAmount());
        $data->setDetractionPaymentMethod($related->getDetractionPaymentMethod());
        $data->setDetractionValueRef($related->getDetractionValueRef());

        $data->setSubtotal($amounts->getSubtotal());
        $data->setTaxableAmount($amounts->getTaxedAmount());
        $data->setIgvAmount($amounts->getTax());
        $data->setRcAmount($amounts->getChargesAmount());
        $data->setDiscountAmount($amounts->getDiscountAmount());

        return;
    }

    /**
     * When a credit note annuls the affected document (reason codes 01, 02, 06),
     * mark the linked SaleRecord as VOIDED — both in the ORM entity and in the
     * sales V2 table — mirroring what SaleVoidUseCase does for Comunicación de Baja.
     */
    private function voidSaleIfCreditNoteAnnuls(TaxDocument $creditNote): void
    {
        if (!in_array($creditNote->getReasonCode(), self::VOID_REASON_CODES, true)) {
            return;
        }

        $saleRecord = $creditNote->getSaleRecord();
        if ($saleRecord === null) {
            return;
        }

        if ($saleRecord->getStatus() === 'VOIDED') {
            return;
        }

        $now = new \DateTimeImmutable();
        $saleRecord->setStatus('VOIDED');
        $saleRecord->setDateUpdated($now);
        $this->entityManager->flush();

        try {
            $this->salesWriteGateway->markVoided($saleRecord, 'Nota de crédito: ' . $creditNote->getFullNumber());
        } catch (\Throwable) {
            // V2 sync failure is non-fatal here; the ORM entity is already updated.
        }
    }
}
