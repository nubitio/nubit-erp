<?php

declare(strict_types=1);

namespace App\State\TaxDocument;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Core\Exception\ServiceException;
use App\Core\Security\Auth\BranchContextService;
use App\Entity\Sale;
use App\Entity\TaxDocument;
use App\Entity\TaxDocumentLine;
use App\Repository\BranchRepository;
use App\Repository\TaxDocumentRepository;
use App\Service\InvoiceAmounts;
use App\Service\InvoiceCalculator;
use App\Service\InvoiceLineInput;
use DateTime;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TaxDocumentProcessor implements ProcessorInterface
{
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
        $creditNoteContext = null;

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
            $creditNoteContext = $this->prepareCreditNoteLines($data);
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

        if ($creditNoteContext !== null) {
            $this->syncCreditNoteAmounts(
                $data,
                $creditNoteContext['amounts'],
                $creditNoteContext['sale']
            );
        }

        return $result;
    }

    /**
     * @return array{amounts: InvoiceAmounts, sale: Sale}
     * @throws ServiceException
     */
    private function prepareCreditNoteLines(TaxDocument $data): array
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

        $connection = $this->taxDocumentRepository->getEntityManager()->getConnection();
        $originalLines = $connection->fetchAllAssociative(
            <<<SQL
SELECT
    line_number,
    product_id,
    product_code,
    product_name,
    product_uom,
    quantity,
    unit_price,
    unit_value,
    tax_type
FROM tax_document_line
WHERE tax_document_id = :taxDocumentId
ORDER BY line_number ASC
SQL,
            ['taxDocumentId' => $related->getId()]
        );

        if ($originalLines === []) {
            throw new ServiceException('El comprobante afectado no tiene ítems registrados.');
        }

        $originalByLine = [];
        foreach ($originalLines as $row) {
            $originalByLine[(int)$row['line_number']] = $row;
        }

        $selected = [];
        foreach ($requestedLines as $req) {
            $lineNumber = (int)($req['lineNumber'] ?? 0);
            $requestedQty = (float)($req['quantity'] ?? 0);
            if ($requestedQty <= 0) {
                continue;
            }
            $original = $originalByLine[$lineNumber] ?? null;
            if ($original === null) {
                throw new ServiceException('El ítem seleccionado no existe en el comprobante afectado.');
            }
            $originalQty = (float)($original['quantity'] ?? 0);
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
                unitPrice: (string)$item['original']['unit_price']
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
            $line->setLineNumber((int)$original['line_number']);
            $line->setProductId($original['product_id']);
            $line->setProductCode($original['product_code']);
            $line->setProductName($original['product_name'] ?? '-');
            $line->setProductUom($original['product_uom']);
            $line->setQuantity($amountLine->quantity);
            $line->setUnitPrice($amountLine->unitPrice);
            $line->setUnitValue($amountLine->unitValue);
            $line->setDiscountAmount($amountLine->discountAmount);
            $line->setTaxedAmount($amountLine->taxableAmount);
            $line->setTaxAmount($amountLine->taxAmount);
            $line->setTaxPercentage($taxPercent);
            $line->setTaxType($original['tax_type'] ?? '10');
            $line->setTotal($amountLine->totalAmount);
            $data->addLine($line);
        }

        $data->setTotal($amounts->getTotal());

        return [
            'amounts' => $amounts,
            'sale' => $sale,
        ];
    }

    private function syncCreditNoteAmounts(
        TaxDocument $data,
        InvoiceAmounts $amounts,
        Sale $sale
    ): void {
        $connection = $this->taxDocumentRepository->getEntityManager()->getConnection();
        $connection->update('tax_document', [
            'currency' => 'PEN',
            'payment_method' => $sale->getFormaPago(),
            'subtotal' => $amounts->getSubtotal(),
            'taxable_amount' => $amounts->getTaxedAmount(),
            'igv_amount' => $amounts->getTax(),
            'rc_amount' => $amounts->getChargesAmount(),
            'total' => $amounts->getTotal(),
            'discount_percent' => $sale->getDescuento() ?? 0,
            'discount_amount' => $amounts->getDiscountAmount(),
            'operation_type' => $sale->getTipoOperacion(),
            'detraction_code' => $sale->getDetraccionCodigo(),
            'detraction_percent' => $sale->getDetraccionPorcentaje(),
            'detraction_amount' => $sale->getDetraccionMonto(),
            'detraction_payment_method' => $sale->getDetraccionMedioPago(),
            'detraction_value_ref' => $sale->getDetraccionValorReferencial(),
        ], [
            'tax_document_id' => $data->getId(),
        ]);
    }
}
