<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillPdfUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
use App\Sale\Domain\SaleRepositoryInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

readonly class InvoiceEmailUseCase
{
    public function __construct(
        private SaleRepositoryInterface $saleRepository,
        private MailerInterface         $mailer,
        private Environment             $twig,
        private Packages                $packages,
        private UrlGeneratorInterface   $urlGenerator,
        private BillPdfUseCase          $billPdfUseCase,
        private FileManager             $fileManager,
        private SettingsService         $settings
    )
    {
    }

    public function __invoke(string $saleId): void
    {
        $invoice = $this->saleRepository->getOneById($saleId);

        $type = match ($invoice['header']['doc_type']) {
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            default => '',
        };

        $logoUrl = $this->packages->getUrl('assets/images/logo.png');
        $invoiceUrl = $this->urlGenerator->generate('invoice_show', ['id' => $saleId], UrlGeneratorInterface::ABSOLUTE_URL);

        $docName = $invoice['header']['doc_series'] . '-' . $invoice['header']['doc_number'];
        $docFullName = $invoice['header']['company_id'] . '-' . $invoice['header']['doc_type'] . '-' . $docName;
        $companyName = $this->settings->get('company_name') ?? $invoice['header']['company_name'];

        $subject = $companyName . ': Tu comprobante de pago electrónico - ' . $docName;
        $xmlPath = $this->settings->get(Constants::COMPANY_ID) . '/' . $docFullName . '.xml';
        $xmlContent = $this->fileManager->read($xmlPath);
        $pdfResponse = $this->billPdfUseCase->__invoke($saleId);
        $pdfContent = $pdfResponse->file;

        $content = $this->twig->render('invoice/tpl/email.html.twig', [
            'logo'            => $logoUrl,
            'type'            => $type,
            'date'            => date('d/m/Y', intval(strtotime(strval($invoice['header']['doc_date'])))),
            'invoice_url'     => $invoiceUrl,
            'series'          => $invoice['header']['doc_series'],
            'number'          => $invoice['header']['doc_number'],
            'total'           => $invoice['header']['doc_sale_amount'],
            'company'         => $companyName,
            'company_address' => $invoice['header']['company_address'],
        ]);

        $email = (new Email())
            ->from('noreply@efact.app')
            ->to(strval($invoice['header']['client_email'] ?: 'johangm90@gmail.com'))
            ->cc(strval($this->settings->get('email_accounting') ?: 'johangm90@gmail.com'))
            ->bcc(strval($this->settings->get('app_alert_recipients') ?: 'johangm90@gmail.com'))
            ->subject($subject)
            ->html($content)
            ->attach($xmlContent, $docFullName . '.xml')
            ->attach($pdfContent, $docFullName . '.pdf');
        $this->mailer->send($email);
    }
}
