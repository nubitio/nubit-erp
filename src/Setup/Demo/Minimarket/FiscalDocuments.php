<?php

declare(strict_types=1);

namespace App\Setup\Demo\Minimarket;

use App\Billing\Entity\TaxDocument;
use App\Billing\Service\InvoiceAmounts;
use App\Sales\Entity\SaleRecord;
use Doctrine\ORM\EntityManagerInterface;

/** Issues the boleta or factura of each sale, and the credit note when the story returns one. Owns the numbering. */
final class FiscalDocuments
{
    private int $boleta = 1;
    private int $factura = 1;
    private int $boletaCreditNote = 1;
    private int $facturaCreditNote = 1;
    private int $voidTicket = 1;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function creditNotesIssued(): int
    {
        return $this->boletaCreditNote + $this->facturaCreditNote - 2;
    }

    /** @return array{0: string, 1: string} series and zero-padded number for the next voucher of this sale */
    public function nextNumber(SalePlan $plan): array
    {
        return (
            $plan->isFactura()
                ? ['F001', sprintf('%08d', $this->factura++)]
                : ['B001', sprintf('%08d', $this->boleta++)]
        );
    }

    public function issue(
        SaleRecord $sale,
        SalePlan $plan,
        InvoiceAmounts $amounts,
        SeedContext $context,
        string $series,
        string $number,
    ): TaxDocument {
        $userId = (int) $context->cashiers[$plan->cashier]->getId();
        $stamp = Clock::utc($plan->at);

        $document = new TaxDocument()
            ->setSaleRecord($sale)
            ->setCustomer($sale->getCliente())
            ->setBranch($context->branch)
            ->setDocumentType($plan->isFactura() ? '01' : '03')
            ->setIssueDate($plan->at)
            ->setSeries($series)
            ->setNumber($number)
            ->setCurrency('PEN')
            ->setPaymentMethod($sale->getFormaPago())
            ->setSubtotal($amounts->getSubtotal())
            ->setTaxableAmount($amounts->getTaxedAmount())
            ->setIgvAmount($amounts->getTax())
            ->setRcAmount($amounts->getChargesAmount())
            ->setDiscountPercent((string) $plan->discountPercent)
            ->setDiscountAmount($amounts->getDiscountAmount())
            ->setOperationType('0101')
            ->setTotal($amounts->getTotal())
            ->setCreatedAt($stamp)
            ->setUpdatedAt($stamp)
            ->setCreatedByUserId($userId);
        $this->applySunat($document, $plan, $series . '-' . $number);

        if ($plan->outcome === SalePlan::VOIDED) {
            $document->setVoidTicket('DEMO-BAJA-' . $this->voidTicket++);
        }
        $this->entityManager->persist($document);

        if ($plan->outcome === SalePlan::CREDIT_NOTE && $plan->outcomeAt !== null) {
            $this->creditNote($document, $plan, $sale, $context, $userId);
        }

        return $document;
    }

    private function applySunat(TaxDocument $document, SalePlan $plan, string $fullNumber): void
    {
        match ($plan->sunat) {
            'accepted' => $document
                ->setSunatStatus('0')
                ->setSunatMessage(sprintf('El comprobante %s ha sido aceptado', $fullNumber)),
            'rejected' => $document
                ->setSunatStatus('2800')
                ->setSunatMessage('El número de RUC del adquiriente no existe en el padrón de SUNAT'),
            default => $document->setSunatStatus(null),
        };
    }

    private function creditNote(
        TaxDocument $original,
        SalePlan $plan,
        SaleRecord $sale,
        SeedContext $context,
        int $userId,
    ): void {
        $series = $plan->isFactura() ? 'FC01' : 'BC01';
        $number = sprintf('%08d', $plan->isFactura() ? $this->facturaCreditNote++ : $this->boletaCreditNote++);
        $issued = $plan->outcomeAt ?? $plan->at;
        $stamp = Clock::utc($issued);

        $note = new TaxDocument()
            ->setSaleRecord($sale)
            ->setCustomer($original->getCustomer())
            ->setBranch($context->branch)
            ->setDocumentType('07')
            ->setIssueDate($issued)
            ->setSeries($series)
            ->setNumber($number)
            ->setCurrency('PEN')
            ->setSubtotal($original->getSubtotal())
            ->setTaxableAmount($original->getTaxableAmount())
            ->setIgvAmount($original->getIgvAmount())
            ->setRcAmount($original->getRcAmount())
            ->setDiscountPercent('0')
            ->setDiscountAmount('0.00')
            ->setOperationType('0101')
            ->setTotal($original->getTotal())
            ->setRelatedTaxDocument($original)
            ->setAffectedDocument($original->getSeries() . '-' . $original->getNumber())
            ->setReasonCode('06')
            ->setReasonDescription('Devolución total')
            ->setCreatedAt($stamp)
            ->setUpdatedAt($stamp)
            ->setCreatedByUserId($userId);
        $note->setSunatStatus('0')->setSunatMessage(sprintf('El comprobante %s-%s ha sido aceptado', $series, $number));
        $this->entityManager->persist($note);
    }
}
