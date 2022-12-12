<?php

namespace App\Invoice\Domain;

use App\Sale\Domain\SaleRepositoryInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class InvoiceEmailUseCase
{
    public function __construct(
        private readonly SaleRepositoryInterface $saleRepository,
        private readonly MailerInterface         $mailer,
    )
    {
    }

    public function __invoke(string $saleId): void
    {
        $invoice = $this->saleRepository->getOneById($saleId);
        $email = (new Email())
            ->from('noreply@efact.app')
            ->to($invoice['header']['client_email'] ?: 'facturas@efact.app')
            ->subject('Factura')
            ->html('<p>Estimado cliente, adjunto encontrará su factura.</p>');
        $this->mailer->send($email);
    }
}