<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Util\ReceiptPayload;
use App\Repository\InvoiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VoucherSearchController extends AbstractController
{
    private const VOUCHER_TYPES = [
        '01' => 'Factura',
        '03' => 'Boleta de Venta',
        '07' => 'Nota de Crédito',
        '08' => 'Nota de Débito',
    ];

    #[Route('/buscar', name: 'voucher_search', methods: ['GET', 'POST'])]
    public function search(Request $request, InvoiceRepository $invoiceRepository): Response
    {
        $result = null;
        $error = null;
        $formData = [
            'ruc' => '',
            'voucher_type' => '01',
            'series' => '',
            'number' => '',
            'date' => '',
            'total' => '',
        ];

        if ($request->isMethod('POST')) {
            $formData = [
                'ruc' => trim($request->request->getString('ruc')),
                'voucher_type' => $request->request->getString('voucher_type'),
                'series' => trim($request->request->getString('series')),
                'number' => trim($request->request->getString('number')),
                'date' => $request->request->getString('date'),
                'total' => trim($request->request->getString('total')),
            ];

            if ($this->validateForm($formData)) {
                $invoice = $invoiceRepository->findBySearchCriteria(
                    companyRuc: $formData['ruc'],
                    voucherType: $formData['voucher_type'],
                    series: $formData['series'],
                    number: $formData['number'],
                    date: $formData['date'],
                    total: $formData['total'],
                );

                if ($invoice !== null) {
                    $result = [
                        'type' => self::VOUCHER_TYPES[$invoice->getVoucherTypeId()] ?? 'Comprobante',
                        'series' => $invoice->getSeries(),
                        'number' => $invoice->getNumber(),
                        'date' => $invoice->getIssuedAt()->format('d/m/Y'),
                        'total' => $invoice->getTotal(),
                        'hash' => $invoice->getDigest(),
                        'status' => $invoice->getVoided() === 0 ? 'VIGENTE' : 'ANULADO',
                        'pdf_url' => ReceiptPayload::publicUrl(
                            $invoice->getVoucherTypeId(),
                            $invoice->getSaleId()
                        ),
                    ];
                } else {
                    $error = 'No se encontró el comprobante con los datos ingresados.';
                }
            } else {
                $error = 'Por favor complete todos los campos correctamente.';
            }
        }

        return $this->render('receipt/search.html.twig', [
            'voucher_types' => self::VOUCHER_TYPES,
            'form_data' => $formData,
            'result' => $result,
            'error' => $error,
        ]);
    }

    /**
     * @param array<string, string> $formData
     */
    private function validateForm(array $formData): bool
    {
        if (strlen($formData['ruc']) !== 11 || !ctype_digit($formData['ruc'])) {
            return false;
        }

        if (!array_key_exists($formData['voucher_type'], self::VOUCHER_TYPES)) {
            return false;
        }

        if (empty($formData['series']) || strlen($formData['series']) !== 4) {
            return false;
        }

        if (empty($formData['number']) || !ctype_digit($formData['number'])) {
            return false;
        }

        if (empty($formData['date'])) {
            return false;
        }

        if (empty($formData['total']) || !is_numeric($formData['total'])) {
            return false;
        }

        return true;
    }
}
