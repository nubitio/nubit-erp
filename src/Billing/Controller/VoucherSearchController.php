<?php

declare(strict_types=1);

namespace App\Billing\Controller;

use App\Billing\Receipt\ReceiptPayload;
use App\Billing\Repository\TaxDocumentRepository;
use App\Efact\Service\CompanyContext;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class VoucherSearchController extends AbstractController
{
    private const string SESSION_FORM = 'voucher_search.form';
    private const string SESSION_RESULT = 'voucher_search.result';
    private const string SESSION_ERROR = 'voucher_search.error';

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
    public function search(
        Request $request,
        TaxDocumentRepository $taxDocumentRepository,
        CompanyContext $companyContext,
    ): Response {
        $session = $request->getSession();
        $defaultFormData = $this->defaultFormData();

        if ($request->isMethod('GET') && $request->query->getBoolean('clear')) {
            $this->clearSearchState($session);

            return $this->redirectToRoute('voucher_search');
        }

        if ($request->isMethod('POST')) {
            return $this->handleSearchPost($request, $taxDocumentRepository, $session);
        }

        return $this->renderSearchPage($companyContext, $session, $defaultFormData);
    }

    /**
     * @throws \DateMalformedStringException
     * @throws Exception
     */
    private function handleSearchPost(
        Request $request,
        TaxDocumentRepository $taxDocumentRepository,
        SessionInterface $session,
    ): Response {
        $formData = [
            'ruc' => trim($request->request->getString('ruc')),
            'voucher_type' => $request->request->getString('voucher_type'),
            'series' => trim($request->request->getString('series')),
            'number' => trim($request->request->getString('number')),
            'date' => $request->request->getString('date'),
            'total' => trim($request->request->getString('total')),
        ];

        $session->set(self::SESSION_FORM, $formData);
        $session->remove(self::SESSION_RESULT);

        if (!$this->validateForm($formData)) {
            $session->set(self::SESSION_ERROR, 'Por favor complete todos los campos correctamente.');

            return $this->redirectToRoute('voucher_search');
        }

        $document = $taxDocumentRepository->findTaxDocumentBySearchCriteria(
            companyRuc: $formData['ruc'],
            voucherType: $formData['voucher_type'],
            series: $formData['series'],
            number: $formData['number'],
            date: $formData['date'],
            total: $formData['total'],
        );

        if ($document === null) {
            $session->set(self::SESSION_ERROR, 'No se encontró el comprobante con los datos ingresados.');

            return $this->redirectToRoute('voucher_search');
        }

        $saleStatusV2 = strtoupper(trim((string) ($document['sale_status_v2'] ?? '')));
        $legacySaleStatus = (int) ($document['sale_status'] ?? 1);
        $isVoided = $saleStatusV2 === 'VOIDED' || in_array($legacySaleStatus, [0, 2], true);

        $currency = strtoupper(trim((string) ($document['currency'] ?? 'PEN')));
        $currencySymbol = self::CURRENCY_SYMBOLS[$currency] ?? $currency;

        $session->remove(self::SESSION_ERROR);
        $session->set(self::SESSION_RESULT, [
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
                strval($document['tax_document_id'])
            ),
        ]);

        return $this->redirectToRoute('voucher_search');
    }

    /**
     * @param array<string, string> $defaultFormData
     */
    private function renderSearchPage(
        CompanyContext $companyContext,
        SessionInterface $session,
        array $defaultFormData,
    ): Response {
        /** @var array<string, string> $formData */
        $formData = $session->get(self::SESSION_FORM, $defaultFormData);
        /** @var array<string, mixed>|null $result */
        $result = $session->get(self::SESSION_RESULT);
        $error = $session->get(self::SESSION_ERROR);

        return $this->render('receipt/search.html.twig', [
            'company_name' => $companyContext->getName(),
            'company_ruc' => $companyContext->getId(),
            'voucher_types' => self::VOUCHER_TYPES,
            'form_data' => $formData,
            'result' => $result,
            'error' => $error,
            'search_clear_url' => $this->generateUrl('voucher_search', ['clear' => 1]),
        ]);
    }

    private function clearSearchState(SessionInterface $session): void
    {
        $session->remove(self::SESSION_FORM);
        $session->remove(self::SESSION_RESULT);
        $session->remove(self::SESSION_ERROR);
    }

    /**
     * @return array<string, string>
     */
    private function defaultFormData(): array
    {
        return [
            'ruc' => '',
            'voucher_type' => '01',
            'series' => '',
            'number' => '',
            'date' => '',
            'total' => '',
        ];
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
