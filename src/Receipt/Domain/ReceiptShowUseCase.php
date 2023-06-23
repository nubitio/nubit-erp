<?php

namespace App\Receipt\Domain;

use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\Invoice\Domain\GetOneVoucherUseCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

readonly class ReceiptShowUseCase
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private GetOneCreditNoteUseCase $getOneNoteUseCase,
    ) {
    }

    public function __invoke(ReceiptRequest $request): string
    {
        $receipt = match ($request->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->makeReceiptForInvoice($request->id),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->makeReceiptForNote($request->id),
        };

        return $this->twig->render('receipt/show.html.twig', [
            'company_name' => $receipt->company,
            'type' => $receipt->type,
            'series' => $receipt->series,
            'number' => $receipt->number,
            'date' => date('d/m/Y', intval(strtotime($receipt->date))),
            'total' => $receipt->total,
            'pdf' => $receipt->pdf,
            'xml' => $receipt->xml,
            'cdr' => $receipt->cdr,
        ]);
    }

    private function makeReceiptForInvoice($id): Receipt
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($id);

        return new Receipt(
            company: $voucher->company_name,
            type: ($voucher->voucher_type) === '01' ? 'FACTURA' : 'BOLETA',
            series: $voucher->voucher_series,
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            total: $voucher->voucher_sale_amount,
            pdf: $this->router->generate('receipt_pdf', ['id' => $voucher->sale_id], UrlGeneratorInterface::ABSOLUTE_URL),
            xml: $this->router->generate('receipt_xml', ['id' => $voucher->sale_id], UrlGeneratorInterface::ABSOLUTE_URL),
            cdr: $this->router->generate('receipt_cdr', ['id' => $voucher->sale_id], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }

    private function makeReceiptForNote($id): Receipt
    {
        $note = $this->getOneNoteUseCase->__invoke($id);

        return new Receipt(
            company: '',
            type: ($note->tipo_id) === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO',
            series: $note->serie,
            number: $note->correlativo,
            date: $note->fecha,
            total: 0,
            pdf: '',
            xml: '',
            cdr: '',
        );
    }
}
