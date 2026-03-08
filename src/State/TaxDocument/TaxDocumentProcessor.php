<?php

declare(strict_types=1);

namespace App\State\TaxDocument;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Entity\TaxDocument;
use App\Entity\TaxDocumentLine;
use App\Repository\BranchRepository;
use App\Repository\TaxDocumentRepository;
use App\EInvoicing\Service\InvoiceCalculator;
use App\EInvoicing\Service\InvoiceLineInput;
use Brick\Math\Exception\MathException;
use DateTime;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<mixed, mixed>
 */
readonly class TaxDocumentProcessor implements ProcessorInterface
{
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
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $isNew = !$data->getId();

        if ($operation instanceof DeleteOperationInterface) {
            if ($data->isProcessed()) {
                throw new ServiceException('No se puede eliminar un documento procesado.');
            }

            return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($isNew && $data->getReasonCode() != null) {
            $data->setDocumentType('07');
            $data->setSale($data->getRelatedTaxDocument()->getSale());
            $data->setAffectedDocument($data->getSale()->getInvoice());
            $data->setCustomer($data->getSale()->getCliente());
            $data->setTotal($data->getSale()->getTotal());
            if ($data->getRelatedTaxDocument()?->isVoided()) {
                throw new ServiceException('No se puede generar nota de crédito para un comprobante anulado.');
            }
            $branchId = $this->branchContextService->getCurrentBranchId();
            $seriesResult = $this->taxDocumentRepository->getTaxDocumentSeriesByBranch($branchId, '07');
            $number = $this->taxDocumentRepository->getNextSequenceNumber($branchId, '07');
            if ($seriesResult->associatedSeries != substr($data->getAffectedDocument(), 0, 4)) {
                throw new ServiceException(
                    'La serie de la nota de crédito no coincide con la serie del documento afectado.'
                );
            }
            $data->setSeries($seriesResult->series);
            $data->setNumber((string)$number);
            $this->prepareCreditNoteLines($data);
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

        $sale = $related->getSale();
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

        $discountPercent = (string)($sale->getDescuento() ?? 0);
        $taxPercent = (string)($sale->getIgv() ?? 0);
        $chargesPercent = (string)($sale->getRecargoConsumo() ?? 0);

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
        $saleCurrencyData = $this->taxDocumentRepository->getSalesCurrencyForSaleId($sale->getId());
        $currency = $related->getCurrency() ?? $saleCurrencyData['currency'] ?? 'PEN';
        $exchangeRate = $related->getExchangeRate() ?? $saleCurrencyData['exchange_rate'];
        if ($currency === 'PEN') {
            $exchangeRate = '1.000000';
        }
        $data->setCurrency($currency);
        $data->setExchangeRate($exchangeRate);
        $data->setPaymentMethod($sale->getFormaPago());
        $data->setSubtotal($amounts->getSubtotal());
        $data->setTaxableAmount($amounts->getTaxedAmount());
        $data->setIgvAmount($amounts->getTax());
        $data->setRcAmount($amounts->getChargesAmount());
        $data->setDiscountPercent($sale->getDescuento() ?? '0');
        $data->setDiscountAmount($amounts->getDiscountAmount());
        $data->setOperationType($sale->getTipoOperacion());
        $data->setDetractionCode($sale->getDetraccionCodigo());
        $data->setDetractionPercent($sale->getDetraccionPorcentaje());
        $data->setDetractionAmount($sale->getDetraccionMonto());
        $data->setDetractionPaymentMethod($sale->getDetraccionMedioPago());
        $data->setDetractionValueRef($sale->getDetraccionValorReferencial());

        return;
    }
}
