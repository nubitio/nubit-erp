<?php

declare(strict_types=1);

namespace App\Billing\Service;

use App\Billing\Entity\TaxDocument;
use App\Billing\Entity\TaxDocumentLine;
use App\Billing\Receipt\Service\VoucherPdfRenderer;
use App\Billing\Shared\Enum\CreditNoteReason;
use DateTimeImmutable;
use DateTimeZone;
use Twig\Environment;

/** Renders the credit note a user is composing as a PDF, without persisting or numbering anything. */
readonly class CreditNotePreviewRenderer
{
    public function __construct(
        private CreditNoteLinePreparer $preparer,
        private CreditNoteNumbering $numbering,
        private VoucherPdfRenderer $pdfRenderer,
        private Environment $twig,
    ) {}

    /**
     * @param list<array{lineNumber: int, quantity: string, restock: bool}> $choices
     */
    public function render(
        TaxDocument $related,
        CreditNoteReason $reason,
        string $reasonDescription,
        array $choices,
    ): string {
        $note = new TaxDocument();
        $note->setRelatedTaxDocument($related);
        $note->setDocumentType('07');
        $note->setReasonCode($reason->value);
        $note->setReasonDescription($reasonDescription !== '' ? $reasonDescription : $reason->description());
        foreach ($choices as $choice) {
            $line = new TaxDocumentLine();
            $line->setLineNumber($choice['lineNumber']);
            $line->setQuantity($choice['quantity']);
            $line->setRestock($choice['restock']);
            $note->addLine($line);
        }
        $this->preparer->prepare($note);

        $identifier = $this->numbering->next($related);
        $company = $related->getBranch()?->getCompany();
        $customer = $related->getCustomer();
        $restocks = $reason === CreditNoteReason::DEVOLUCION_ITEM;

        $html = $this->twig->render('invoice/credit_note_preview.html.twig', [
            'companyName' => $company?->getBusinessName(),
            'companyRuc' => $company?->getRuc(),
            'companyAddress' => $related->getBranch()?->getAddress() ?? $company?->getFiscalAddress(),
            'series' => $identifier['series'],
            'number' => str_pad($identifier['number'], length: 8, pad_string: '0', pad_type: STR_PAD_LEFT),
            'customerName' => $customer?->getBusinessName(),
            'customerDocument' => $customer?->getRuc(),
            'issueDate' => new DateTimeImmutable(timezone: new DateTimeZone('America/Lima')),
            'currency' => $note->getCurrency() ?? 'PEN',
            'affectedDocument' => $related->getFullNumber(),
            'reasonCode' => $reason->value,
            'reasonText' => $reason->description(),
            'reasonDescription' => $note->getReasonDescription(),
            'lines' => array_map(static fn(TaxDocumentLine $line): array => [
                'quantity' => $line->getQuantity(),
                'uom' => $line->getProductUom(),
                'code' => $line->getProductCode(),
                'name' => $line->getProductName(),
                'unitPrice' => $line->getUnitPrice(),
                'total' => $line->getTotal(),
                'restock' => $restocks && $line->isRestock(),
            ], array_values($note->getLines()->toArray())),
            'taxable' => $note->getTaxableAmount(),
            'igv' => $note->getIgvAmount(),
            'total' => $note->getTotal(),
        ]);

        return $this->pdfRenderer->renderPdf($html);
    }
}
