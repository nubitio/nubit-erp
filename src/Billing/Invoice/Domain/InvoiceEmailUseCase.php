<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

use App\Billing\Repository\TaxDocumentRepository;
use App\Billing\Sunat\Domain\BillPdfUseCase;
use App\Efact\Config\EfactConstants as Constants;
use App\Efact\Service\SettingsService;
use App\Organization\Service\CompanyContext;
use Doctrine\DBAL\Exception;
use League\Flysystem\FilesystemException;
use Nubit\Platform\Filesystem\FileManager;
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
        private string $mailerFrom = 'noreply@nubit.pe',
    ) {}

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws FilesystemException
     * @throws LoaderError
     * @throws Exception
     */
    public function __invoke(string $taxDocumentId, ?string $recipient = null): void
    {
        $voucher = $this->taxDocumentRepository->getVoucherByTaxDocumentId($taxDocumentId);

        $to = $recipient ?: $voucher->customer_email;
        if (!$to) {
            throw InvoiceEmailException::missingRecipient();
        }

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
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $companyId = $this->companyContext->getId();
        $docFullName = $companyId . '-' . $voucher->voucher_type . '-' . $docName;
        $companyName = $this->companyContext->getName();

        $subject = $companyName . ': Tu comprobante de pago electrónico - ' . $docName;
        $xmlPath = Constants::INVOICE_DIR . '/' . $docFullName . '/' . $docFullName . '.xml';
        try {
            $xmlContent = $this->fileManager->read($xmlPath);
        } catch (FilesystemException $e) {
            throw InvoiceEmailException::xmlNotReady($docName, $e);
        }
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
            ->from($this->mailerFrom)
            ->to($to)
            ->subject($subject)
            ->html($content)
            ->attach($xmlContent, $docFullName . '.xml')
            ->attach($pdfContent, $docFullName . '.pdf');

        // Copies only go to addresses the tenant configured; there is no built-in fallback inbox.
        $accounting = strval($this->settings->get('email_accounting') ?: '');
        if ($accounting !== '') {
            $email->cc($accounting);
        }
        $alerts = strval($this->settings->get('app_alert_recipients') ?: '');
        if ($alerts !== '') {
            $email->bcc($alerts);
        }

        $this->mailer->send($email);
    }
}
