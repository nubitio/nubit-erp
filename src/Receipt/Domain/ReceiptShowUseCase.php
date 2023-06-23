<?php

namespace App\Receipt\Domain;

use App\Core\Domain\Exception\ServiceException;
use App\CreditNote\Domain\GetOneCreditNoteUseCase;
use App\Invoice\Domain\GetOneVoucherUseCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class ReceiptShowUseCase
{
    public function __construct(
        private Environment $twig,
        private UrlGeneratorInterface $router,
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private GetOneCreditNoteUseCase $getOneNoteUseCase,
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws ServiceException
     * @throws LoaderError
     */
    public function __invoke(ReceiptRequest $request): string
    {
        $receipt = match ($request->type) {
            ReceiptType::FACTURA, ReceiptType::BOLETA => $this->makeReceiptForInvoice($request),
            ReceiptType::NOTA_CREDITO, ReceiptType::NOTA_DEBITO => $this->makeReceiptForNote($request),
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

    private function makeReceiptForInvoice($payload): Receipt
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($payload);


        return new Receipt(
            company: $voucher->company_name,
            type: ($voucher->voucher_type) === '01' ? 'FACTURA' : 'BOLETA',
            series: $voucher->voucher_series,
            number: $voucher->voucher_number,
            date: $voucher->voucher_date,
            total: $voucher->voucher_sale_amount,
            pdf: $this->router->generate('receipt_pdf', ['payload' => $payload], UrlGeneratorInterface::ABSOLUTE_URL),
            xml: '',
            cdr: ''
        );
    }


    /**
     * @throws ServiceException
     */
    private function makeReceiptForNote($id): Receipt
    {
        $note = $this->getOneNoteUseCase->__invoke($id);

        return new Receipt(
            company: '',
            type: ($note->tipo_id) === '07' ? 'NOTA DE CRÉDITO' : 'NOTA DE DÉBITO',
            series: $note->serie,
            number: $note->correlativo,
            date: $note->fecha->format('Y-m-d'),
            total: 0,
            pdf: $this->router->generate('receipt_pdf', ['id' => $note->nota_id], UrlGeneratorInterface::ABSOLUTE_URL),
            xml: $this->router->generate('receipt_xml', ['id' => $note->nota_id], UrlGeneratorInterface::ABSOLUTE_URL),
            cdr: $this->router->generate('receipt_cdr', ['id' => $note->nota_id], UrlGeneratorInterface::ABSOLUTE_URL),
        );
    }
}
