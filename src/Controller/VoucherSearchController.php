<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Util\ReceiptPayload;
use App\Billing\Repository\TaxDocumentRepository;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VoucherSearchController extends AbstractController
{
    private const array VOUCHER_TYPES = [
        '01' => 'Factura',
        '03' => 'Boleta de Venta',
        '07' => 'Nota de Crédito',
        '08' => 'Nota de Débito',
    ];

    private const array CURRENCY_SYMBOLS = [
        'PEN' => 'S/',
        'USD' => '$',
        'EUR' => '€',
    ];

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    #[Route('/buscar', name: 'voucher_search', methods: ['GET', 'POST'])]
    public function search(Request $request, TaxDocumentRepository $taxDocumentRepository): Response
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
                $document = $taxDocumentRepository->findTaxDocumentBySearchCriteria(
                    companyRuc: $formData['ruc'],
                    voucherType: $formData['voucher_type'],
                    series: $formData['series'],
                    number: $formData['number'],
                    date: $formData['date'],
                    total: $formData['total'],
                );

                if ($document !== null) {
                    $saleStatusV2 = strtoupper(trim((string) ($document['sale_status_v2'] ?? '')));
                    $legacySaleStatus = (int) ($document['sale_status'] ?? 1);
                    $isVoided = $saleStatusV2 === 'VOIDED' || in_array($legacySaleStatus, [0, 2], true);

                    $currency = strtoupper(trim((string) ($document['currency'] ?? 'PEN')));
                    $currencySymbol = self::CURRENCY_SYMBOLS[$currency] ?? $currency;

                    $result = [
                        'type' => self::VOUCHER_TYPES[strval($document['document_type'])] ?? 'Comprobante',
                        'series' => $document['series'],
                        'number' => $document['number'],
                        'date' => date('d/m/Y', strtotime(strval($document['issue_date']))),
                        'total' => $document['total'],
                        'hash' => $document['digest'],
                        'status' => $isVoided ? 'ANULADO' : 'ACEPTADO',
                        'currency_symbol' => $currencySymbol,
                        'pdf_url' => ReceiptPayload::publicUrl(
                            strval($document['document_type']),
                            strval($document['movimiento_id'])
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
