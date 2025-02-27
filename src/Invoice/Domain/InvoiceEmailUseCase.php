<?php

namespace App\Invoice\Domain;

use App\Billing\Domain\BillPdfUseCase;
use App\Core\Domain\Util\Constants;
use App\Core\Infrastructure\Service\FileManager;
use App\Core\Infrastructure\Service\SettingsService;
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
        private GetOneVoucherUseCase $getOneVoucherUseCase,
        private MailerInterface $mailer,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
        private BillPdfUseCase $billPdfUseCase,
        private FileManager $fileManager,
        private SettingsService $settings,
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
    public function __invoke(string $saleId): void
    {
        $voucher = $this->getOneVoucherUseCase->__invoke($saleId);

        $type = match ($voucher->voucher_type) {
            '01' => 'FACTURA',
            '03' => 'BOLETA',
            default => '',
        };

        $payload = [
            'type' => $voucher->voucher_type,
            'id' => $saleId,
        ];

        $payload = base64_encode(strval(json_encode($payload)));

        $invoiceUrl = $this->urlGenerator->generate('receipt_show', ['payload' => $payload], UrlGeneratorInterface::ABSOLUTE_URL);

        $docName = $voucher->voucher_series . '-' . $voucher->voucher_number;
        $docFullName = $voucher->company_id . '-' . $voucher->voucher_type . '-' . $docName;
        $companyName = $this->settings->get('company_name') ?? $voucher->company_name;

        $subject = $companyName . ': Tu comprobante de pago electrónico - ' . $docName;
        $xmlPath = $this->settings->get(Constants::COMPANY_ID) . '/' . $docFullName . '.xml';
        $xmlContent = $this->fileManager->read($xmlPath);
        $pdfResponse = $this->billPdfUseCase->__invoke($saleId);
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

        $email = (new Email())
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
