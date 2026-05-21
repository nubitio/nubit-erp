<?php

declare(strict_types=1);

namespace App\Billing\Receipt\Domain;

use Nubit\Platform\Exception\NotFoundException;
use Nubit\Platform\Exception\ServiceException;
use App\Efact\Service\CompanyContext;
use App\Billing\Repository\TaxDocumentRepository;

readonly class ReceiptShowUseCase
{
    public function __construct(
        private TaxDocumentRepository $taxDocumentRepository,
        private CompanyContext $companyContext,
    ) {
    }

    /**
     * @throws ServiceException
     */
    public function __invoke(ReceiptRequest $request): Receipt
    {
        return match ($request->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->makeReceiptForInvoice($request->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->makeReceiptForNote($request->id),
        };
    }

    private function makeReceiptForInvoice(string $id): Receipt
    {
        $invoice = $this->taxDocumentRepository->find($id);
        if ($invoice === null) {
            throw NotFoundException::forResource('Invoice', $id);
        }
        $companyName = $this->companyContext->getName();

        return new Receipt(
            company: $companyName,
            type: ($invoice->getDocumentType()) === '01' ? 'FACTURA' : 'BOLETA',
            series: strval($invoice->getSeries()),
            number: strval($invoice->getNumber()),
            date: strval(\DateTimeImmutable::createFromInterface($invoice->getIssueDate())->format('Y-m-d')),
            total: floatval($invoice->gettotal()),
            currency: strval($invoice->getCurrency() ?? 'PEN'),
        );
    }

    private function makeReceiptForNote(string $id): Receipt
    {
        $note = $this->taxDocumentRepository->find($id);
        if ($note === null) {
            throw NotFoundException::forResource('Note', $id);
        }
        $companyName = $this->companyContext->getName();

        return new Receipt(
            company: $companyName,
            type: ($note->getDocumentType()) === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO',
            series: strval($note->getSeries()),
            number: strval($note->getNumber()),
            date: strval(\DateTimeImmutable::createFromInterface($note->getIssueDate())->format('Y-m-d')),
            total: floatval($note->getTotal()),
            currency: strval($note->getCurrency() ?? 'PEN'),
        );
    }
}
