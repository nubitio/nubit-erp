<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillPdfUseCase;
use App\Sale\Domain\SaleRepositoryInterface;
use App\Service\SettingsService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class InvoiceEmailUseCase
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly MailerInterface         $mailer,
        private readonly SettingsService         $settingsService,
        private readonly Environment             $twig,
        private readonly Packages                $packages,
        private readonly UrlGeneratorInterface   $urlGenerator,
        private readonly BillPdfUseCase          $billPdfUseCase,
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
        $companyName = $this->settingsService->get('company_name') ?? $invoice['header']['company_name'];

        $subject = $companyName . ': Tu comprobante de pago electrónico - ' . $docName;
        $xmlPath = $_ENV['EFACT_DIR'] . $docFullName . '.xml';
        $pdfResponse = $this->billPdfUseCase->__invoke($saleId);
        $pdfFile = $pdfResponse->file;

        $content = $this->twig->render('invoice/tpl/email.html.twig', [
            'logo'            => $logoUrl,
            'type'            => $type,
            'date'            => date('d/m/Y', strtotime($invoice['header']['doc_date'])),
            'invoice_url'     => $invoiceUrl,
            'series'          => $invoice['header']['doc_series'],
            'number'          => $invoice['header']['doc_number'],
            'total'           => $invoice['header']['doc_sale_amount'],
            'company'         => $companyName,
            'company_address' => $invoice['header']['company_address'],
        ]);

        $email = (new Email())
            ->from('noreply@efact.app')
            ->to($invoice['header']['client_email'] ?: 'johangm90@gmail.com')
            ->cc($this->settingsService->get('email_accounting'))
            ->bcc($this->settingsService->get('app_alert_recipients'))
            ->subject($subject)
            ->html($content)
            ->attachFromPath($xmlPath)
            ->attach($pdfFile, $docFullName . '.pdf');
        $this->mailer->send($email);
    }
}
