<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use App\Billing\Sunat\Domain\BillPdfUseCase;
use Nubit\Platform\Filesystem\FileManager;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Efact\Service\CompanyContext;
use App\Billing\Repository\TaxDocumentRepository;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class InvoiceEmailUseCase
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private BillPdfUseCase $billPdfUseCase,
        private FileManager $fileManager,
        private SettingsService $settings,
        private CompanyContext $companyContext,
        private TaxDocumentRepository $taxDocumentRepository,
    ) {
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws FilesystemException
     * @throws LoaderError
     * @throws Exception
     */
    public function __invoke(string $taxDocumentId): void
    {
        $voucher = $this->taxDocumentRepository->getVoucherByTaxDocumentId($taxDocumentId);

        $type = match ($voucher->voucher_type) {
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            default => '',
        };

        $payload = [
            'type' => $voucher->voucher_type,
            'id' => $taxDocumentId,
        ];

        $payload = base64_encode(strval(json_encode($payload)));

        $invoiceUrl = $this->urlGenerator->generate(
            'receipt_show',
            ['payload' => $payload],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $companyId = $this->companyContext->getId();
        $docFullName = $companyId . '-' . $voucher->voucher_type . '-' . $docName;
        $companyName = $this->companyContext->getName();

        $subject = $companyName . ': Tu comprobante de pago electrónico - ' . $docName;
        $xmlPath = Constants::INVOICE_DIR . '/' . $docFullName . '/' . $docFullName . '.xml';
        $xmlContent = $this->fileManager->read($xmlPath);
        $pdfResponse = $this->billPdfUseCase->__invoke($taxDocumentId);
        $pdfContent = $pdfResponse->file;

        $content = $this->twig->render('invoice/tpl/email.html.twig', [
            'type' => $type,
            'date' => date('d/m/Y', intval(strtotime(strval($voucher->voucher_date)))),
            'invoice_url' => $invoiceUrl,
            'series' => $voucher->voucher_series,
            'number' => $voucher->voucher_number,
            'total' => $voucher->voucher_sale_amount,
            'company' => $companyName,
            'company_address' => $voucher->company_address,
        ]);

        $email = new Email()
            ->from('noreply@efact.app')
            ->to($voucher->customer_email ?: 'johangm90@gmail.com')
            ->cc(strval($this->settings->get('email_accounting') ?: 'johangm90@gmail.com'))
            ->bcc(strval($this->settings->get('app_alert_recipients') ?: 'johangm90@gmail.com'))
            ->subject($subject)
            ->html($content)
            ->attach($xmlContent, $docFullName . '.xml')
            ->attach($pdfContent, $docFullName . '.pdf');
        $this->mailer->send($email);
    }
}
